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

/**
 * Holt die Texte aus `tl_translation_fields` in die Felder zurueck.
 *
 * Die Vorgaengererweiterung photoalbums2 hat mehrere Textfelder ueber die
 * Erweiterung `craffft/contao-translation-fields` mehrsprachig gefuehrt. In den
 * Feldern selbst stand dann nicht der Text, sondern nur eine Nummer, die auf
 * eine Zeile in `tl_translation_fields` verwies.
 *
 * Dieses Bundle kennt keine Mehrsprachigkeit mehr. Ohne Umzug wuerde im
 * Backend und im Frontend die nackte Nummer erscheinen — genau das Bild, das
 * betroffene Installationen zeigen. Die Migration ersetzt deshalb jede solche
 * Nummer durch den zugehoerigen Text.
 *
 * Betroffen sind:
 *
 * - `tl_photoalbums2_album`: event, place, photographer, description
 * - `tl_content`: pa2Teaser
 * - `tl_module`: pa2Teaser
 *
 * Die Tabelle `tl_translation_fields` bleibt unangetastet: Sie kann noch von
 * anderen Erweiterungen benutzt werden, und ein zweiter Anlauf der Migration
 * soll moeglich bleiben.
 */
class TranslationFieldsMigration extends AbstractMigration
{
	/**
	 * Die Tabellen und Felder, in denen Verweise stehen koennen.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const FIELDS = array(
		'tl_photoalbums2_album' => array('event', 'place', 'photographer', 'description'),
		'tl_content'            => array('pa2Teaser'),
		'tl_module'             => array('pa2Teaser'),
	);

	/**
	 * Die bevorzugte Sprache; erst danach wird irgendeine genommen.
	 *
	 * @var string
	 */
	private const PREFERRED_LANGUAGE = 'de';

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
	 * Liefert den im Contao Manager angezeigten Namen der Migration.
	 *
	 * @return string Eine kurze deutsche Beschreibung
	 */
	public function getName(): string
	{
		return 'Fotoalben: Texte aus tl_translation_fields in die Felder zuruecknehmen';
	}

	/**
	 * Prueft, ob es ueberhaupt etwas umzuziehen gibt.
	 *
	 * @return bool true, wenn mindestens ein Feld noch eine Verweisnummer
	 *              enthaelt, zu der es einen Text gibt
	 */
	public function shouldRun(): bool
	{
		if (!$this->tableExists('tl_translation_fields'))
		{
			return false;
		}

		foreach (self::FIELDS as $strTable => $arrFields)
		{
			foreach ($arrFields as $strField)
			{
				if (!$this->isTextColumn($strTable, $strField))
				{
					continue;
				}

				if (!empty($this->findReferences($strTable, $strField)))
				{
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Fuehrt den Umzug durch.
	 *
	 * @return MigrationResult Das Ergebnis mit der Zahl der geaenderten
	 *                         Datensaetze je Tabelle und Feld
	 */
	public function run(): MigrationResult
	{
		$arrMessages = array();

		foreach (self::FIELDS as $strTable => $arrFields)
		{
			foreach ($arrFields as $strField)
			{
				if (!$this->isTextColumn($strTable, $strField))
				{
					continue;
				}

				$intCount = $this->migrateField($strTable, $strField);

				if ($intCount > 0)
				{
					$arrMessages[] = sprintf('%s.%s: %d Datensaetze', $strTable, $strField, $intCount);
				}
			}
		}

		if (empty($arrMessages))
		{
			return $this->createResult(true, 'Es waren keine Verweise auf tl_translation_fields zu ersetzen.');
		}

		return $this->createResult(true, 'Texte aus tl_translation_fields uebernommen — '.implode(', ', $arrMessages).'.');
	}

	/**
	 * Ersetzt in einem Feld alle Verweisnummern durch den zugehoerigen Text.
	 *
	 * @param string $strTable Name der Tabelle
	 * @param string $strField Name des Feldes
	 *
	 * @return int Zahl der geaenderten Datensaetze
	 */
	private function migrateField(string $strTable, string $strField): int
	{
		$arrRows = $this->findReferences($strTable, $strField);

		if (empty($arrRows))
		{
			return 0;
		}

		$intCount = 0;

		foreach ($arrRows as $arrRow)
		{
			$strContent = $this->findTranslation((int) $arrRow['value']);

			if (null === $strContent)
			{
				continue;
			}

			$this->connection->update(
				$strTable,
				array($strField => $strContent),
				array('id' => $arrRow['id'])
			);

			++$intCount;
		}

		return $intCount;
	}

	/**
	 * Sucht in einem Feld alle Werte, die wie eine Verweisnummer aussehen.
	 *
	 * Geprueft wird zusaetzlich, ob es zu der Nummer ueberhaupt eine Zeile in
	 * `tl_translation_fields` gibt — sonst wuerde eine Beschreibung, die
	 * zufaellig nur aus Ziffern besteht, faelschlich als Verweis gelten.
	 *
	 * @param string $strTable Name der Tabelle
	 * @param string $strField Name des Feldes
	 *
	 * @return array<int, array<string, mixed>> Datensatznummer und Feldwert
	 */
	private function findReferences(string $strTable, string $strField): array
	{
		$strSql = sprintf(
			'SELECT t.id AS id, t.%1$s AS value
			 FROM %2$s t
			 WHERE t.%1$s REGEXP \'^[0-9]+$\'
			   AND t.%1$s <> \'0\'
			   AND EXISTS (SELECT 1 FROM tl_translation_fields f WHERE f.fid = t.%1$s)',
			$this->quoteIdentifier($strField),
			$this->quoteIdentifier($strTable)
		);

		return $this->connection->fetchAllAssociative($strSql);
	}

	/**
	 * Liest den Text zu einer Verweisnummer.
	 *
	 * Bevorzugt wird die deutsche Fassung; gibt es sie nicht, wird die Zeile
	 * mit der kleinsten Datensatznummer genommen. Ein leerer Text gilt als
	 * „nichts gefunden“, damit ein vorhandener Wert nicht durch Leere ersetzt
	 * wird.
	 *
	 * @param int $intFid Die Verweisnummer
	 *
	 * @return string|null Der Text oder null, wenn es keinen gibt
	 */
	private function findTranslation(int $intFid): ?string
	{
		$strContent = $this->connection->fetchOne(
			'SELECT content FROM tl_translation_fields WHERE fid = ? AND language = ? LIMIT 1',
			array($intFid, self::PREFERRED_LANGUAGE)
		);

		if (false === $strContent || null === $strContent || '' === $strContent)
		{
			$strContent = $this->connection->fetchOne(
				'SELECT content FROM tl_translation_fields WHERE fid = ? AND content <> \'\' ORDER BY id ASC LIMIT 1',
				array($intFid)
			);
		}

		if (false === $strContent || null === $strContent || '' === $strContent)
		{
			return null;
		}

		return (string) $strContent;
	}

	/**
	 * Prueft, ob eine Tabelle vorhanden ist.
	 *
	 * @param string $strTable Name der Tabelle
	 *
	 * @return bool true, wenn die Tabelle existiert
	 */
	private function tableExists(string $strTable): bool
	{
		return $this->connection->createSchemaManager()->tablesExist(array($strTable));
	}

	/**
	 * Prueft, ob eine Spalte vorhanden **und** vom Typ Text ist.
	 *
	 * Der Typ ist wichtig: `tl_content.pa2Teaser` war unter photoalbums2 eine
	 * Ganzzahlspalte. Contao fuehrt Migrationen einmal **vor** und einmal
	 * **nach** dem Angleichen des Datenbankschemas aus. Liefe die Migration
	 * schon im ersten Durchgang, wuerde der Text in die Ganzzahlspalte
	 * geschrieben und dabei zu 0 — der Inhalt waere unwiederbringlich weg.
	 * Deshalb wird die Spalte im ersten Durchgang uebersprungen; im zweiten
	 * ist sie laengst in eine Textspalte umgewandelt.
	 *
	 * @param string $strTable Name der Tabelle
	 * @param string $strField Name der Spalte
	 *
	 * @return bool true, wenn die Spalte existiert und Text aufnehmen kann
	 */
	private function isTextColumn(string $strTable, string $strField): bool
	{
		if (!$this->tableExists($strTable))
		{
			return false;
		}

		$arrColumns = $this->connection->createSchemaManager()->listTableColumns($strTable);
		$strKey = strtolower($strField);

		if (!isset($arrColumns[$strKey]))
		{
			return false;
		}

		$strType = strtolower((new \ReflectionClass($arrColumns[$strKey]->getType()))->getShortName());

		return false !== strpos($strType, 'string') || false !== strpos($strType, 'text') || false !== strpos($strType, 'blob') || false !== strpos($strType, 'binary');
	}

	/**
	 * Prueft einen Bezeichner, bevor er in eine Abfrage geschrieben wird.
	 *
	 * Die Namen stammen zwar alle aus der Konstanten oben und sind damit fest
	 * verdrahtet; die Pruefung steht trotzdem hier, damit eine spaetere
	 * Erweiterung der Liste nicht unbemerkt eine Luecke aufreisst.
	 *
	 * @param string $strName Tabellen- oder Spaltenname
	 *
	 * @return string Derselbe Name
	 *
	 * @throws \InvalidArgumentException Wenn der Name Sonderzeichen enthaelt
	 */
	private function quoteIdentifier(string $strName): string
	{
		if (!preg_match('/^[A-Za-z0-9_]+$/', $strName))
		{
			throw new \InvalidArgumentException(sprintf('Ungueltiger Bezeichner "%s"', $strName));
		}

		return $strName;
	}
}
