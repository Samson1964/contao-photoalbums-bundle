<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Download;

/**
 * Schreibt ein ZIP-Archiv unmittelbar in die Ausgabe.
 *
 * Gedacht für Alben mit vielen und großen Dateien. Die übliche Lösung —
 * `ZipArchive` eine Datei bauen lassen und sie danach ausliefern — scheitert
 * daran gleich dreifach: Sie braucht den Platz noch einmal auf der Platte, der
 * Besucher wartet ohne jedes Lebenszeichen, bis das Archiv fertig ist, und bei
 * einigen tausend Fotos läuft vorher die Laufzeitbegrenzung ab.
 *
 * Diese Klasse schreibt stattdessen Byte für Byte in die Ausgabe: Der Download
 * beginnt sofort, es wird nichts zwischengespeichert, und der Speicherbedarf
 * bleibt bei der Größe eines Blocks — gleichgültig, ob das Album zehn Fotos
 * enthält oder zehntausend.
 *
 * Drei Entscheidungen im Format machen das möglich:
 *
 * * **Keine Komprimierung** (Methode 0, „store“). Fotos und Videos sind
 *   bereits komprimiert; sie noch einmal durch Deflate zu schicken kostet viel
 *   Rechenzeit und spart nichts.
 * * **Nachgestellte Prüfsumme** (Data Descriptor, Flag Bit 3). Die CRC-32 einer
 *   Datei steht erst fest, wenn sie ganz gelesen ist. Ohne diesen Kunstgriff
 *   müsste jede Datei zweimal gelesen werden — einmal für die Prüfsumme, einmal
 *   für die Ausgabe.
 * * **ZIP64 durchgehend**. Ein klassisches ZIP endet bei 4 GB und 65535
 *   Einträgen. Ein Album mit tausend Fotos ist davon nicht weit entfernt, und
 *   die Grenze nur manchmal zu überschreiten wäre die schlechtere Wahl: Dann
 *   führe der Weg für große Archive durch Code, den nie jemand ausprobiert hat.
 *
 * Weil bei „store“ jede Länge von vornherein feststeht, lässt sich die Größe
 * des fertigen Archivs **vorab ausrechnen** ({@see self::getSize()}). Der
 * Browser bekommt damit ein `Content-Length` und kann Fortschritt und
 * Restdauer anzeigen.
 *
 * Aufbau nach der Spezifikation „APPNOTE.TXT“ 6.3.x der PKWARE Inc.
 */
class ZipStream
{
	/**
	 * Blockgröße beim Lesen und Ausgeben in Bytes.
	 *
	 * 256 KiB ist groß genug, dass der Aufwand je Block nicht ins Gewicht
	 * fällt, und klein genug, dass die Ausgabe gleichmäßig fließt.
	 *
	 * @var int
	 */
	private const CHUNK_SIZE = 262144;

	/**
	 * Länge des lokalen Dateikopfes ohne Namen und Zusatzfeld.
	 *
	 * @var int
	 */
	private const LOCAL_HEADER_SIZE = 30;

	/**
	 * Länge des ZIP64-Zusatzfeldes im lokalen Dateikopf.
	 *
	 * Zwei 64-Bit-Längen (unkomprimiert, komprimiert) plus vier Bytes Kopf.
	 *
	 * @var int
	 */
	private const LOCAL_EXTRA_SIZE = 20;

	/**
	 * Länge der nachgestellten Prüfsummenangabe in der ZIP64-Form.
	 *
	 * Kennung, CRC-32 und zwei 64-Bit-Längen.
	 *
	 * @var int
	 */
	private const DESCRIPTOR_SIZE = 24;

	/**
	 * Länge eines Eintrags im Inhaltsverzeichnis ohne Namen und Zusatzfeld.
	 *
	 * @var int
	 */
	private const CENTRAL_HEADER_SIZE = 46;

	/**
	 * Länge des ZIP64-Zusatzfeldes im Inhaltsverzeichnis.
	 *
	 * Drei 64-Bit-Werte (unkomprimiert, komprimiert, Position) plus vier Bytes
	 * Kopf.
	 *
	 * @var int
	 */
	private const CENTRAL_EXTRA_SIZE = 28;

	/**
	 * Länge des Abschlusses: ZIP64-Ende, ZIP64-Verweis und klassisches Ende.
	 *
	 * @var int
	 */
	private const END_SIZE = 98;

	/**
	 * Zeitstempel für den 1. Januar 1980, 0 Uhr UTC.
	 *
	 * Vor diesem Tag kann ein ZIP-Datum nichts ausdrücken; das Feld zählt die
	 * Jahre ab 1980 in sieben Bit.
	 *
	 * @var int
	 */
	private const DOS_EPOCH = 315532800;

	/**
	 * Die aufzunehmenden Einträge in der Reihenfolge der Ausgabe.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $arrEntries = array();

	/**
	 * Bereits vergebene Namen, um Doppelungen zu erkennen.
	 *
	 * Schlüssel ist der kleingeschriebene Name: Windows und macOS
	 * unterscheiden beim Entpacken nicht zwischen Groß- und Kleinschreibung,
	 * `Bild.jpg` und `bild.jpg` überschrieben einander dort.
	 *
	 * @var array<string, bool>
	 */
	private $arrUsedNames = array();

	/**
	 * Nimmt eine Datei von der Platte in das Archiv auf.
	 *
	 * Gelesen wird die Datei erst beim Ausgeben. Größe und Änderungszeit
	 * werden dagegen **jetzt** ermittelt, weil die vorausberechnete Gesamtgröße
	 * darauf beruht; ändert sich die Datei danach noch, gleicht
	 * {@see self::writeFileData()} das aus.
	 *
	 * @param string $strPath Absoluter Pfad der Datei
	 * @param string $strName Name im Archiv, Verzeichnisse mit `/` getrennt
	 *
	 * @return bool true, wenn die Datei aufgenommen wurde; false, wenn es sie
	 *              nicht gibt oder sie sich nicht lesen lässt
	 */
	public function addFile(string $strPath, string $strName): bool
	{
		if (!is_file($strPath) || !is_readable($strPath))
		{
			return false;
		}

		$intSize = filesize($strPath);

		if (false === $intSize)
		{
			return false;
		}

		$this->arrEntries[] = array(
			'name' => $this->makeUniqueName($strName),
			'path' => $strPath,
			'text' => null,
			'size' => (int) $intSize,
			'time' => (int) filemtime($strPath),
		);

		return true;
	}

	/**
	 * Nimmt einen im Speicher erzeugten Text in das Archiv auf.
	 *
	 * Gedacht für kleine Beigaben wie die Infodatei eines Albums — der Inhalt
	 * bleibt bis zur Ausgabe im Speicher und sollte deshalb überschaubar sein.
	 *
	 * @param string $strContent Der Inhalt, bereits in der gewünschten Kodierung
	 * @param string $strName    Name im Archiv
	 * @param int    $intTime    Änderungszeit als Unix-Zeitstempel
	 *
	 * @return void
	 */
	public function addText(string $strContent, string $strName, int $intTime): void
	{
		$this->arrEntries[] = array(
			'name' => $this->makeUniqueName($strName),
			'path' => null,
			'text' => $strContent,
			'size' => \strlen($strContent),
			'time' => $intTime,
		);
	}

	/**
	 * Sorgt dafür, dass jeder Name im Archiv nur einmal vorkommt.
	 *
	 * Ein Album kann Fotos aus mehreren Ordnern enthalten, und dort heißen
	 * Dateien gerne gleich. Ohne Eingriff überschriebe das Entpackprogramm die
	 * eine mit der anderen, und der Besucher bekäme weniger Fotos, als das
	 * Album zeigt. Doppelte Namen erhalten deshalb eine laufende Nummer vor
	 * der Endung: `bild.jpg`, `bild_2.jpg`, `bild_3.jpg`.
	 *
	 * @param string $strName Der gewünschte Name
	 *
	 * @return string Ein Name, den es im Archiv noch nicht gibt
	 */
	private function makeUniqueName(string $strName): string
	{
		$strName = str_replace('\\', '/', $strName);
		$strName = ltrim($strName, '/');

		if ('' === $strName)
		{
			$strName = 'datei';
		}

		$strKey = strtolower($strName);

		if (!isset($this->arrUsedNames[$strKey]))
		{
			$this->arrUsedNames[$strKey] = true;

			return $strName;
		}

		$intDot = strrpos($strName, '.');
		$strStem = false === $intDot ? $strName : substr($strName, 0, $intDot);
		$strSuffix = false === $intDot ? '' : substr($strName, $intDot);

		$i = 2;

		while (isset($this->arrUsedNames[strtolower($strStem.'_'.$i.$strSuffix)]))
		{
			++$i;
		}

		$strUnique = $strStem.'_'.$i.$strSuffix;
		$this->arrUsedNames[strtolower($strUnique)] = true;

		return $strUnique;
	}

	/**
	 * Liefert die Anzahl der aufgenommenen Einträge.
	 *
	 * @return int Die Anzahl; 0, wenn noch nichts aufgenommen wurde
	 */
	public function count(): int
	{
		return \count($this->arrEntries);
	}

	/**
	 * Berechnet die Größe des fertigen Archivs im Voraus.
	 *
	 * Möglich ist das nur, weil nicht komprimiert wird: Jede Länge im Archiv
	 * steht damit von vornherein fest. Der Wert geht als `Content-Length` an
	 * den Browser, der daraus Fortschritt und Restdauer anzeigen kann.
	 *
	 * @return int Die Größe in Bytes
	 */
	public function getSize(): int
	{
		$intSize = self::END_SIZE;

		foreach ($this->arrEntries as $arrEntry)
		{
			$intNameLength = \strlen($arrEntry['name']);

			// Lokaler Kopf, Daten und nachgestellte Prüfsumme
			$intSize += self::LOCAL_HEADER_SIZE + $intNameLength + self::LOCAL_EXTRA_SIZE;
			$intSize += $arrEntry['size'];
			$intSize += self::DESCRIPTOR_SIZE;

			// Eintrag im Inhaltsverzeichnis am Ende des Archivs
			$intSize += self::CENTRAL_HEADER_SIZE + $intNameLength + self::CENTRAL_EXTRA_SIZE;
		}

		return $intSize;
	}

	/**
	 * Schreibt das gesamte Archiv in die Ausgabe.
	 *
	 * Die Methode gibt unmittelbar aus; Kopfzeilen müssen also bereits gesendet
	 * sein. Sie schließt zuvor alle Ausgabepuffer, weil ein aktiver Puffer den
	 * Sinn des Streamens aufhöbe: Er sammelte das ganze Archiv im Speicher.
	 *
	 * Bricht der Besucher den Download ab, endet das Skript von selbst —
	 * `ignore_user_abort` bleibt ausdrücklich aus.
	 *
	 * @return void
	 */
	public function send(): void
	{
		while (ob_get_level() > 0)
		{
			ob_end_flush();
		}

		$intOffset = 0;
		$arrCentral = array();

		foreach ($this->arrEntries as $arrEntry)
		{
			$intStart = $intOffset;

			$strHeader = $this->buildLocalHeader($arrEntry);
			echo $strHeader;
			$intOffset += \strlen($strHeader);

			$intCrc = null === $arrEntry['path']
				? crc32((string) $arrEntry['text'])
				: $this->writeFileData($arrEntry);

			if (null === $arrEntry['path'])
			{
				echo $arrEntry['text'];
			}

			$intOffset += $arrEntry['size'];

			echo $this->buildDescriptor($intCrc, $arrEntry['size']);
			$intOffset += self::DESCRIPTOR_SIZE;

			$arrEntry['crc'] = $intCrc;
			$arrEntry['offset'] = $intStart;
			$arrCentral[] = $arrEntry;

			$this->flushOutput();
		}

		$intCentralOffset = $intOffset;
		$intCentralSize = 0;

		foreach ($arrCentral as $arrEntry)
		{
			$strRecord = $this->buildCentralHeader($arrEntry);
			echo $strRecord;
			$intCentralSize += \strlen($strRecord);
		}

		echo $this->buildEnd(\count($arrCentral), $intCentralSize, $intCentralOffset);

		$this->flushOutput();
	}

	/**
	 * Gibt den Inhalt einer Datei aus und berechnet dabei die Prüfsumme.
	 *
	 * Ausgegeben werden **genau** so viele Bytes, wie beim Aufnehmen der Datei
	 * ermittelt wurden. Ist die Datei inzwischen gewachsen, wird der Rest
	 * abgeschnitten; ist sie geschrumpft oder mittendrin verschwunden, wird mit
	 * Null-Bytes aufgefüllt. Das klingt eigenwillig, ist aber der Punkt, an dem
	 * sich entscheidet, ob der Download gelingt: Eine einzige Abweichung
	 * verschöbe alles Folgende, das angekündigte `Content-Length` stimmte nicht
	 * mehr, und der Browser bräche das ganze Archiv als beschädigt ab. So
	 * bleibt im schlimmsten Fall eine Datei unbrauchbar und alle anderen heil.
	 *
	 * @param array<string, mixed> $arrEntry Der Eintrag mit `path` und `size`
	 *
	 * @return int Die CRC-32-Prüfsumme der tatsächlich ausgegebenen Bytes
	 */
	private function writeFileData(array $arrEntry): int
	{
		$objHash = hash_init('crc32b');
		$intRemaining = (int) $arrEntry['size'];

		$resFile = @fopen((string) $arrEntry['path'], 'rb');

		if (false !== $resFile)
		{
			while ($intRemaining > 0 && !feof($resFile))
			{
				$strChunk = fread($resFile, min(self::CHUNK_SIZE, $intRemaining));

				if (false === $strChunk || '' === $strChunk)
				{
					break;
				}

				hash_update($objHash, $strChunk);
				echo $strChunk;

				$intRemaining -= \strlen($strChunk);

				$this->flushOutput();
			}

			fclose($resFile);
		}

		// Fehlende Bytes auffüllen, damit die angekündigte Länge stimmt
		while ($intRemaining > 0)
		{
			$strPad = str_repeat("\0", min(self::CHUNK_SIZE, $intRemaining));

			hash_update($objHash, $strPad);
			echo $strPad;

			$intRemaining -= \strlen($strPad);
		}

		return (int) hexdec(hash_final($objHash, false));
	}

	/**
	 * Schiebt die Ausgabe zum Besucher durch.
	 *
	 * Ohne diesen Anstoß sammelte PHP die Ausgabe und schickte sie erst am
	 * Ende — der Download begänne dann gar nicht sofort.
	 *
	 * @return void
	 */
	private function flushOutput(): void
	{
		if (ob_get_level() > 0)
		{
			ob_flush();
		}

		flush();
	}

	/**
	 * Baut den lokalen Dateikopf, der jedem Eintrag vorangeht.
	 *
	 * Die beiden Längenfelder tragen `0xFFFFFFFF`; die wirklichen Werte stehen
	 * im ZIP64-Zusatzfeld, und da sie beim Schreiben des Kopfes noch nicht
	 * feststehen, dort zunächst als Null. Erst die nachgestellte
	 * Prüfsummenangabe nennt sie.
	 *
	 * @param array<string, mixed> $arrEntry Der Eintrag
	 *
	 * @return string Die Bytes des Kopfes samt Namen und Zusatzfeld
	 */
	private function buildLocalHeader(array $arrEntry): string
	{
		list($intTime, $intDate) = self::getDosTime((int) $arrEntry['time']);

		$strHeader = "PK\x03\x04";
		$strHeader .= pack('v', 45);                 // benötigte Fassung: 4.5 wegen ZIP64
		$strHeader .= pack('v', 0x0008 | 0x0800);    // Prüfsumme folgt nach; Name in UTF-8
		$strHeader .= pack('v', 0);                  // Verfahren: keine Komprimierung
		$strHeader .= pack('v', $intTime);
		$strHeader .= pack('v', $intDate);
		$strHeader .= pack('V', 0);                  // Prüfsumme, steht noch nicht fest
		$strHeader .= pack('V', 0xFFFFFFFF);         // Länge komprimiert: siehe ZIP64
		$strHeader .= pack('V', 0xFFFFFFFF);         // Länge unkomprimiert: siehe ZIP64
		$strHeader .= pack('v', \strlen($arrEntry['name']));
		$strHeader .= pack('v', self::LOCAL_EXTRA_SIZE);
		$strHeader .= $arrEntry['name'];

		// ZIP64-Zusatzfeld
		$strHeader .= pack('v', 0x0001);
		$strHeader .= pack('v', 16);
		$strHeader .= pack('P', 0);
		$strHeader .= pack('P', 0);

		return $strHeader;
	}

	/**
	 * Baut die Prüfsummenangabe, die hinter den Daten eines Eintrags steht.
	 *
	 * @param int $intCrc  Die CRC-32-Prüfsumme der Daten
	 * @param int $intSize Die Länge der Daten in Bytes
	 *
	 * @return string Die Bytes der Angabe, immer {@see self::DESCRIPTOR_SIZE} lang
	 */
	private function buildDescriptor(int $intCrc, int $intSize): string
	{
		$strDescriptor = "PK\x07\x08";
		$strDescriptor .= pack('V', $intCrc);
		$strDescriptor .= pack('P', $intSize);       // komprimiert
		$strDescriptor .= pack('P', $intSize);       // unkomprimiert

		return $strDescriptor;
	}

	/**
	 * Baut den Eintrag eines Elements im Inhaltsverzeichnis.
	 *
	 * Das Inhaltsverzeichnis steht am Ende des Archivs und ist das, was ein
	 * Entpackprogramm zuerst liest. Hier sind Prüfsumme, Längen und Position
	 * bekannt und werden vollständig eingetragen.
	 *
	 * @param array<string, mixed> $arrEntry Der Eintrag samt `crc` und `offset`
	 *
	 * @return string Die Bytes des Verzeichniseintrags
	 */
	private function buildCentralHeader(array $arrEntry): string
	{
		list($intTime, $intDate) = self::getDosTime((int) $arrEntry['time']);

		$strRecord = "PK\x01\x02";
		$strRecord .= pack('v', 0x031E);             // erzeugt unter Unix, Fassung 3.0
		$strRecord .= pack('v', 45);
		$strRecord .= pack('v', 0x0008 | 0x0800);
		$strRecord .= pack('v', 0);
		$strRecord .= pack('v', $intTime);
		$strRecord .= pack('v', $intDate);
		$strRecord .= pack('V', (int) $arrEntry['crc']);
		$strRecord .= pack('V', 0xFFFFFFFF);
		$strRecord .= pack('V', 0xFFFFFFFF);
		$strRecord .= pack('v', \strlen($arrEntry['name']));
		$strRecord .= pack('v', self::CENTRAL_EXTRA_SIZE);
		$strRecord .= pack('v', 0);                  // Länge des Kommentars
		$strRecord .= pack('v', 0);                  // Nummer des Datenträgers
		$strRecord .= pack('v', 0);                  // innere Merkmale
		$strRecord .= pack('V', 0100644 << 16);      // äußere Merkmale: gewöhnliche Datei, 0644
		$strRecord .= pack('V', 0xFFFFFFFF);         // Position: siehe ZIP64
		$strRecord .= $arrEntry['name'];

		// ZIP64-Zusatzfeld
		$strRecord .= pack('v', 0x0001);
		$strRecord .= pack('v', 24);
		$strRecord .= pack('P', (int) $arrEntry['size']);
		$strRecord .= pack('P', (int) $arrEntry['size']);
		$strRecord .= pack('P', (int) $arrEntry['offset']);

		return $strRecord;
	}

	/**
	 * Baut den Abschluss des Archivs.
	 *
	 * Er besteht aus drei Teilen: dem ZIP64-Ende mit den wirklichen Werten, dem
	 * Verweis darauf und dem klassischen Ende, dessen Felder allesamt auf
	 * „siehe ZIP64“ stehen. Ältere Programme finden so wenigstens den Anfang
	 * und melden verständlich, dass sie das Archiv nicht lesen können.
	 *
	 * @param int $intCount  Anzahl der Einträge
	 * @param int $intSize   Länge des Inhaltsverzeichnisses in Bytes
	 * @param int $intOffset Position des Inhaltsverzeichnisses im Archiv
	 *
	 * @return string Die Bytes des Abschlusses, immer {@see self::END_SIZE} lang
	 */
	private function buildEnd(int $intCount, int $intSize, int $intOffset): string
	{
		// ZIP64-Ende des Inhaltsverzeichnisses
		$strEnd = "PK\x06\x06";
		$strEnd .= pack('P', 44);                    // Länge des Restes dieses Satzes
		$strEnd .= pack('v', 0x031E);
		$strEnd .= pack('v', 45);
		$strEnd .= pack('V', 0);                     // Nummer dieses Datenträgers
		$strEnd .= pack('V', 0);                     // Datenträger mit dem Verzeichnis
		$strEnd .= pack('P', $intCount);
		$strEnd .= pack('P', $intCount);
		$strEnd .= pack('P', $intSize);
		$strEnd .= pack('P', $intOffset);

		// Verweis auf das ZIP64-Ende
		$strEnd .= "PK\x06\x07";
		$strEnd .= pack('V', 0);
		$strEnd .= pack('P', $intOffset + $intSize);
		$strEnd .= pack('V', 1);

		// Klassisches Ende, alle Felder auf „siehe ZIP64“
		$strEnd .= "PK\x05\x06";
		$strEnd .= pack('v', 0xFFFF);
		$strEnd .= pack('v', 0xFFFF);
		$strEnd .= pack('v', 0xFFFF);
		$strEnd .= pack('v', 0xFFFF);
		$strEnd .= pack('V', 0xFFFFFFFF);
		$strEnd .= pack('V', 0xFFFFFFFF);
		$strEnd .= pack('v', 0);

		return $strEnd;
	}

	/**
	 * Rechnet einen Unix-Zeitstempel in das Datumsformat von MS-DOS um.
	 *
	 * Das ZIP-Format stammt aus den Achtzigern und zählt die Jahre ab 1980 in
	 * sieben Bit, die Sekunden in fünf — es kennt also nur gerade Sekunden und
	 * nichts vor 1980. Ein älterer Zeitstempel wird auf den 1. Januar 1980
	 * gesetzt; das kommt durchaus vor, wenn jemand die Änderungszeit einer
	 * Datei auf das Aufnahmedatum eines historischen Fotos gesetzt hat.
	 *
	 * @param int $intTimestamp Der Unix-Zeitstempel
	 *
	 * @return array{0: int, 1: int} Uhrzeit und Datum, beide als 16-Bit-Wert
	 */
	private static function getDosTime(int $intTimestamp): array
	{
		if ($intTimestamp < self::DOS_EPOCH)
		{
			$intTimestamp = self::DOS_EPOCH;
		}

		$arrDate = getdate($intTimestamp);

		$intDate = (($arrDate['year'] - 1980) << 9) | ($arrDate['mon'] << 5) | $arrDate['mday'];
		$intTime = ($arrDate['hours'] << 11) | ($arrDate['minutes'] << 5) | ($arrDate['seconds'] >> 1);

		return array($intTime, $intDate);
	}
}
