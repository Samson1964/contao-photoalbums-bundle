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
 * betroffene Installationen zeigen.
 *
 * Steht in einem Feld eine reine Zahl, wird sie deshalb so behandelt:
 *
 * 1. **Zu der Nummer gibt es eine Zeile mit Text** — der Text tritt an die
 *    Stelle der Nummer.
 * 2. **Die Zeile gibt es, sie ist aber leer** — dann war das Feld auch unter
 *    photoalbums2 leer und die Nummer ist nichts als ein Ueberbleibsel des
 *    Uebersetzungsverfahrens. Sie kommt weg, das Feld bleibt leer.
 * 3. **Zu der Nummer gibt es gar keine Zeile** — dann ist die Zahl kein
 *    Verweis, sondern ein echter Wert (ein Ereignis „1968" etwa). Sie bleibt
 *    unangetastet.
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
	 * Hoechstlaenge eines Feldwertes, der noch als Verweis in Frage kommt.
	 *
	 * Eine nackte Nummer ist vier bis fuenf Zeichen lang, eine in Markup
	 * verpackte („<p>2071</p>") etwa elf. Der grosszuegige Wert laesst auch
	 * geschachteltes Markup durch, haelt aber echte Fliesstexte davon ab,
	 * ueberhaupt erst geprueft zu werden.
	 *
	 * @var int
	 */
	private const MAX_REFERENCE_LENGTH = 100;

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
	 * Zwischenspeicher fuer die Frage, ob es eine Zeile zu einer fid gibt.
	 *
	 * @var array<int, bool>
	 */
	private $arrExisting = array();

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
		$intReplaced = 0;
		$intCleared = 0;
		$intChained = 0;

		foreach (self::FIELDS as $strTable => $arrFields)
		{
			foreach ($arrFields as $strField)
			{
				$arrResult = $this->analyse($strTable, $strField);

				$intReplaced += $arrResult['replaced'];
				$intCleared += $arrResult['cleared'];
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
					$arrMessages[] = sprintf(
						'%s.%s: %d uebernommen, %d geleert',
						$strTable,
						$strField,
						$arrResult['replaced'],
						$arrResult['cleared']
					);
				}
			}
		}

		if (empty($arrMessages))
		{
			$strResult = 'Es waren keine Verweise auf tl_translation_fields zu bearbeiten.';
		}
		else
		{
			$strResult = sprintf(
				'%d Texte aus tl_translation_fields uebernommen, %d leere Verweise entfernt — %s.',
				$intReplaced,
				$intCleared,
				implode('; ', $arrMessages)
			);
		}

		// Uebergangene Verweise werden gemeldet, weil in diesen Feldern
		// weiterhin eine nackte Nummer steht und jemand von Hand nachsehen muss
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
	 * @return array{updates: array<int, array{id: mixed, content: string}>, replaced: int, cleared: int, chained: int}
	 *         Die zu aendernden Datensaetze sowie die Zahl der uebernommenen,
	 *         der geleerten und der uebergangenen Verweise
	 */
	private function analyse(string $strTable, string $strField): array
	{
		$arrUpdates = array();
		$intReplaced = 0;
		$intCleared = 0;
		$intChained = 0;

		foreach ($this->findReferences($strTable, $strField) as $arrRow)
		{
			$strContent = $this->findTranslation($arrRow['fid']);

			// Die Zeile gibt es, sie ist aber leer: Dann war das Feld auch unter
			// photoalbums2 leer, und die Nummer ist nichts als ein Ueberbleibsel
			// des Uebersetzungsverfahrens. Sie kommt weg.
			if (null === $strContent)
			{
				$arrUpdates[] = array('id' => $arrRow['id'], 'content' => '');
				++$intCleared;

				continue;
			}

			if (preg_match('/^[0-9]+$/', $strContent) && null !== $this->findTranslation((int) $strContent))
			{
				++$intChained;

				continue;
			}

			$arrUpdates[] = array('id' => $arrRow['id'], 'content' => $strContent);
			++$intReplaced;
		}

		return array('updates' => $arrUpdates, 'replaced' => $intReplaced, 'cleared' => $intCleared, 'chained' => $intChained);
	}

	/**
	 * Sucht in einem Feld alle Werte, die wie eine Verweisnummer aussehen.
	 *
	 * Erkannt wird die nackte Nummer **und** eine in Markup verpackte, etwa
	 * `<p>2071</p>`. Letzteres entsteht bei den Feldern mit Rich-Text-Editor:
	 * Wer ein Album im Backend oeffnet und speichert, bekommt die rohe Nummer
	 * vom Editor in einen Absatz gepackt. Ohne diese zweite Form bliebe genau
	 * dort die Zahl im Frontend stehen.
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
	 * @return array<int, array{id: mixed, fid: int}> Datensatznummer und die
	 *                                                darin gefundene Verweisnummer
	 */
	private function findReferences(string $strTable, string $strField): array
	{
		if (!$this->isTextColumn($strTable, $strField))
		{
			return array();
		}

		// Vorauswahl in der Datenbank: nicht leer, nicht "0", kurz genug fuer
		// eine verpackte Nummer und mit mindestens einer Ziffer. Die Laenge
		// haelt echte Fliesstexte von vornherein draussen; entschieden wird
		// danach in PHP, weil sich Markup dort verlaesslicher abstreifen laesst
		// als mit einem SQL-Muster.
		$strSql = sprintf(
			'SELECT t.id AS id, t.%1$s AS value
			 FROM %2$s t
			 WHERE t.%1$s IS NOT NULL
			   AND t.%1$s <> \'\'
			   AND t.%1$s <> \'0\'
			   AND CHAR_LENGTH(t.%1$s) <= %3$d
			   AND t.%1$s REGEXP \'[0-9]\'',
			$this->quoteIdentifier($strField),
			$this->quoteIdentifier($strTable),
			self::MAX_REFERENCE_LENGTH
		);

		$arrReferences = array();

		foreach ($this->connection->fetchAllAssociative($strSql) as $arrRow)
		{
			$intFid = $this->extractReference((string) $arrRow['value']);

			if (null === $intFid || !$this->referenceExists($intFid))
			{
				continue;
			}

			$arrReferences[] = array('id' => $arrRow['id'], 'fid' => $intFid);
		}

		return $arrReferences;
	}

	/**
	 * Schaelt aus einem Feldwert die Verweisnummer heraus.
	 *
	 * Erlaubt ist ausschliesslich Markup und Leerraum um die Ziffern herum.
	 * Sobald noch irgendein anderes Zeichen uebrig bleibt — ein Buchstabe, ein
	 * Punkt, ein zweites Wort —, ist es keine Verweisnummer, sondern ein Text,
	 * der zufaellig Ziffern enthaelt.
	 *
	 * Eine Grenze bleibt: Ein Text aus **zwei** Absaetzen, die je nur Ziffern
	 * enthalten (`<p>20</p><p>71</p>`), ergibt beim Abstreifen des Markups
	 * ebenfalls eine Zahl. Ein solcher Wert ist nicht sinnvoll konstruierbar,
	 * und selbst dann muesste die entstehende Nummer zufaellig eine Zeile in
	 * `tl_translation_fields` haben, damit ueberhaupt etwas geschieht.
	 *
	 * @param string $strValue Der rohe Feldwert
	 *
	 * @return int|null Die Verweisnummer oder null, wenn es keine ist
	 */
	private function extractReference(string $strValue): ?int
	{
		$strBare = strip_tags($strValue);
		$strBare = html_entity_decode($strBare, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		// Das geschuetzte Leerzeichen aus dem Editor ist kein normaler Leerraum
		$strBare = str_replace("\xC2\xA0", ' ', $strBare);
		$strBare = trim($strBare);

		if ('' === $strBare || '0' === $strBare || !preg_match('/^[0-9]+$/', $strBare))
		{
			return null;
		}

		return (int) $strBare;
	}

	/**
	 * Prueft, ob es zu einer Nummer ueberhaupt eine Uebersetzungszeile gibt.
	 *
	 * Das unterscheidet einen echten Verweis von einer Zahl, die einfach als
	 * Wert im Feld steht — ein Ereignis „1968" etwa.
	 *
	 * @param int $intFid Die Verweisnummer
	 *
	 * @return bool true, wenn mindestens eine Zeile mit dieser fid existiert
	 */
	private function referenceExists(int $intFid): bool
	{
		if (!isset($this->arrExisting[$intFid]))
		{
			$this->arrExisting[$intFid] = (bool) $this->connection->fetchOne(
				'SELECT 1 FROM tl_translation_fields WHERE fid = ? LIMIT 1',
				array($intFid)
			);
		}

		return $this->arrExisting[$intFid];
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
