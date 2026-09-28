<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\AlbumAlias;

/**
 * Schreibt Album-Aliase mit Umlauten und anderen Sonderzeichen um.
 *
 * Seit dem Umstieg auf Contao 4 hat `StringUtil::standardize()` Umlaute im
 * Alias stehen lassen; so sind Aliase wie `dsam-düsseldorf-2023` entstanden.
 * Diese Migration bringt sie auf die Form, die alle älteren Alben schon haben:
 * `dsam-duesseldorf-2023`.
 *
 * Angefasst werden **nur** Aliase mit Zeichen außerhalb von ASCII. Aliase,
 * die heute etwas anders gebildet würden, aber bereits reines ASCII sind,
 * bleiben stehen — jede Änderung bräche Verweise von außen, ohne etwas zu
 * gewinnen.
 *
 * Alte Adressen laufen nicht ins Leere: Die Foto-Ansicht erkennt einen alten
 * Alias, rechnet den neuen aus und leitet mit 301 weiter (siehe
 * {@see AlbumAlias::findRenamed()}).
 *
 * Kollidiert ein neuer Alias mit einem vorhandenen, bekommt er die
 * Albumnummer angehängt — dieselbe Regel, die auch das Backend bei
 * automatisch erzeugten Aliassen anwendet.
 *
 * `shouldRun()` und `run()` stützen sich auf dieselbe Auswertung
 * {@see self::analyse()}. Sonst meldete die eine Arbeit, die die andere nicht
 * erledigt, und der Installationsassistent liefe endlos — genau das ist der
 * Migration der Übersetzungsfelder einmal passiert.
 */
class AlbumAliasMigration extends AbstractMigration
{
	/**
	 * Die Datenbankverbindung.
	 *
	 * @var Connection
	 */
	private $connection;

	/**
	 * @param Connection $connection Wird per Autowiring gesetzt
	 */
	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
	}

	/**
	 * Liefert die Beschriftung im Installationsassistenten.
	 *
	 * @return string Die Beschriftung
	 */
	public function getName(): string
	{
		return 'Fotoalben: Umlaute in Album-Aliassen umschreiben (ü → ue, ß → ss)';
	}

	/**
	 * Sagt, ob es etwas umzuschreiben gibt.
	 *
	 * @return bool true, wenn mindestens ein Alias Sonderzeichen enthält
	 */
	public function shouldRun(): bool
	{
		if (!$this->connection->createSchemaManager()->tablesExist(array('tl_photoalbums2_album')))
		{
			return false;
		}

		return !empty($this->analyse());
	}

	/**
	 * Schreibt die betroffenen Aliase um.
	 *
	 * @return MigrationResult Das Ergebnis samt Liste „alt → neu“, damit im
	 *                         Protokoll steht, welche Adressen sich geändert haben
	 */
	public function run(): MigrationResult
	{
		$arrChanges = $this->analyse();
		$arrLines = array();

		foreach ($arrChanges as $intId => $arrChange)
		{
			$this->connection->update('tl_photoalbums2_album', array('alias' => $arrChange['new']), array('id' => $intId));
			$arrLines[] = $arrChange['old'].' → '.$arrChange['new'];
		}

		return $this->createResult(
			true,
			\count($arrChanges).' Album-Aliase umgeschrieben: '.implode(', ', $arrLines)
		);
	}

	/**
	 * Ermittelt, welche Aliase wie umzuschreiben sind.
	 *
	 * Die Kollisionsprüfung berücksichtigt auch die in diesem Lauf neu
	 * vergebenen Aliase: Hießen zwei Alben `köln-2020` und `koeln-2020`, darf
	 * das erste nicht ebenfalls `koeln-2020` werden.
	 *
	 * @return array<int, array{old: string, new: string}> Album-ID => alter und
	 *                                                      neuer Alias
	 */
	private function analyse(): array
	{
		$arrRows = $this->connection->fetchAllAssociative("SELECT id, alias FROM tl_photoalbums2_album WHERE alias!=''");

		$arrTaken = array();

		foreach ($arrRows as $arrRow)
		{
			$arrTaken[(string) $arrRow['alias']] = (int) $arrRow['id'];
		}

		$arrChanges = array();

		foreach ($arrRows as $arrRow)
		{
			$intId = (int) $arrRow['id'];
			$strOld = (string) $arrRow['alias'];

			if (!AlbumAlias::needsRewrite($strOld))
			{
				continue;
			}

			$strNew = AlbumAlias::generate($strOld);

			if ('' === $strNew)
			{
				$strNew = 'id-'.$intId;
			}

			if (isset($arrTaken[$strNew]) && $arrTaken[$strNew] !== $intId)
			{
				$strNew .= '-'.$intId;
			}

			unset($arrTaken[$strOld]);
			$arrTaken[$strNew] = $intId;

			$arrChanges[$intId] = array('old' => $strOld, 'new' => $strNew);
		}

		return $arrChanges;
	}
}
