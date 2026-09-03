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
 *
 * **Wichtig fuer die Fehlersuche:** `shouldRun()` und `run()` benutzen
 * dieselbe Methode {@see self::analyse()}. Das ist keine Kosmetik,
 * sondern Bedingung: Meldete `shouldRun()` Arbeit, die `run()` dann nicht
 * erledigen kann, bliebe die Migration ewig als „ausstehend“ stehen und der
 * Installationsassistent liefe im Kreis.
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
	 * Zwischenspeicher fuer aufgeloeste Verweisnummern.
	 *
	 * Ein Verweis kommt haeufig in mehreren Datensaetzen vor; ohne diesen
	 * Speicher liefe je Datensatz eine eigene Abfrage.
	 *
	 * @var array<int, string|null>
	 */
	private $arrTranslations = array();

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
	 * Prueft, ob es etwas umzuziehen gibt, das sich auch wirklich umziehen laesst.
	 *
	 * Ein Verweis, zu dem es keinen brauchbaren Text gibt, zaehlt hier
	 * ausdruecklich **nicht** als Arbeit — sonst bliebe die Migration
	 * dauerhaft ausstehend.
	 *
	 * @return bool true, wenn mindestens ein Feld ersetzt werden kann
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
				if (!empty($this->analyse($strTable, $strField)['updates']))
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
		$intWithoutText = 0;
		$intChained = 0;

		foreach (self::FIELDS as $strTable => $arrFields)
		{
			foreach ($arrFields as $strField)
			{
				$arrResult = $this->analyse($strTable, $strField);

				$intWithoutText += $arrResult['withoutText'];
				$intChained += $arrResult['chained'];

				foreach ($arrResult['updates'] as $arrUpdate)
				{
					$this->connection->update(
						$strTable,
						array($strField => $arrUpdate['content']),
						array('id' => $arrUpdate['id'])
					);
				}

				if (!empty($arrResult['updates']))
				{
					$intCount = \count($arrResult['updates']);
					$arrMessages[] = sprintf('%s.%s: %d %s', $strTable, $strField, $intCount, 1 === $intCount ? 'Datensatz' : 'Datensaetze');
				}
			}
		}

		$strResult = empty($arrMessages)
			? 'Es waren keine Verweise auf tl_translation_fields zu ersetzen.'
			: 'Texte aus tl_translation_fields uebernommen — '.implode(', ', $arrMessages).'.';

		// Uebergangene Verweise werden gemeldet, weil in diesen Feldern
		// weiterhin eine nackte Nummer steht und jemand von Hand nachsehen muss
		if ($intWithoutText > 0)
		{
			$strResult .= 1 === $intWithoutText
				? ' Zu einem Verweis steht in tl_translation_fields kein Text; dort bleibt die Nummer stehen.'
				: sprintf(' Zu %d Verweisen steht in tl_translation_fields kein Text; dort bleibt die Nummer stehen.', $intWithoutText);
		}

		if ($intChained > 0)
		{
			$strResult .= 1 === $intChained
				? ' Ein Verweis zeigt auf einen Text, der selbst wieder wie eine Verweisnummer aussieht; er bleibt unangetastet.'
				: sprintf(' %d Verweise zeigen auf einen Text, der selbst wieder wie eine Verweisnummer aussieht; sie bleiben unangetastet.', $intChained);
		}

		return $this->createResult(true, $strResult);
	}

	/**
	 * Ermittelt die Datensaetze, die sich in einem Feld wirklich ersetzen lassen.
	 *
	 * Ausgeschlossen werden zwei Faelle:
	 *
	 * 1. Zu der Verweisnummer gibt es keinen oder nur einen leeren Text. Ihn
	 *    durch nichts zu ersetzen waere schlimmer als die Nummer stehen zu
	 *    lassen — die Nummer ist der letzte Anhaltspunkt, um den Text von Hand
	 *    wiederzufinden.
	 * 2. Der gefundene Text besteht selbst nur aus Ziffern **und** liesse sich
	 *    seinerseits als Verweis aufloesen. Nach dem Ersetzen saehe das Feld
	 *    wieder wie ein Verweis aus, und die Migration wuerde beim naechsten
	 *    Lauf erneut zuschlagen.
	 *
	 * @param string $strTable Name der Tabelle
	 * @param string $strField Name des Feldes
	 *
	 * @return array{updates: array<int, array{id: mixed, content: string}>, withoutText: int, chained: int}
	 *         Die zu aendernden Datensaetze sowie die Zahl der uebergangenen
	 *         Verweise, nach Grund getrennt
	 */
	private function analyse(string $strTable, string $strField): array
	{
		$arrUpdates = array();
		$intWithoutText = 0;
		$intChained = 0;

		foreach ($this->findReferences($strTable, $strField) as $arrRow)
		{
			$strContent = $this->findTranslation((int) $arrRow['value']);

			if (null === $strContent)
			{
				++$intWithoutText;

				continue;
			}

			if (preg_match('/^[0-9]+$/', $strContent) && null !== $this->findTranslation((int) $strContent))
			{
				++$intChained;

				continue;
			}

			$arrUpdates[] = array('id' => $arrRow['id'], 'content' => $strContent);
		}

		return array('updates' => $arrUpdates, 'withoutText' => $intWithoutText, 'chained' => $intChained);
	}

	/**
	 * Sucht in einem Feld alle Werte, die wie eine Verweisnummer aussehen.
	 *
	 * Geprueft wird zusaetzlich, ob es zu der Nummer ueberhaupt eine Zeile in
	 * `tl_translation_fields` gibt — sonst wuerde eine Beschreibung, die
	 * zufaellig nur aus Ziffern besteht, faelschlich als Verweis gelten.
	 *
	 * Fehlt die Spalte oder ist sie noch eine Ganzzahlspalte, kommt eine leere
	 * Liste zurueck (siehe {@see self::isTextColumn()}).
	 *
	 * @param string $strTable Name der Tabelle
	 * @param string $strField Name des Feldes
	 *
	 * @return array<int, array<string, mixed>> Datensatznummer und Feldwert
	 */
	private function findReferences(string $strTable, string $strField): array
	{
		if (!$this->isTextColumn($strTable, $strField))
		{
			return array();
		}

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
	 * Bevorzugt wird die deutsche Fassung; gibt es sie nicht oder ist sie leer,
	 * wird die erste Zeile mit Inhalt genommen. Ein leerer Text gilt als
	 * „nichts gefunden“, damit ein vorhandener Wert nicht durch Leere ersetzt
	 * wird.
	 *
	 * @param int $intFid Die Verweisnummer
	 *
	 * @return string|null Der Text oder null, wenn es keinen gibt
	 */
	private function findTranslation(int $intFid): ?string
	{
		if (\array_key_exists($intFid, $this->arrTranslations))
		{
			return $this->arrTranslations[$intFid];
		}

		$strContent = $this->connection->fetchOne(
			'SELECT content FROM tl_translation_fields WHERE fid = ? AND language = ? AND content <> \'\' LIMIT 1',
			array($intFid, self::PREFERRED_LANGUAGE)
		);

		if (false === $strContent || null === $strContent || '' === $strContent)
		{
			$strContent = $this->connection->fetchOne(
				'SELECT content FROM tl_translation_fields WHERE fid = ? AND content <> \'\' ORDER BY id ASC LIMIT 1',
				array($intFid)
			);
		}

		$this->arrTranslations[$intFid] = (false === $strContent || null === $strContent || '' === $strContent)
			? null
			: (string) $strContent;

		return $this->arrTranslations[$intFid];
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
