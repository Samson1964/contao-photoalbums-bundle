<?php

declare(strict_types=1);

/*
 * Prüfwerkzeug für den ZIP-Schreiber des Fotoalben-Bundles.
 *
 * Geprüft wird die Klasse `Download\ZipStream` ganz allein — ohne Contao, ohne
 * Datenbank, ohne Webserver. Zwei Dinge muss sie können, und beide lassen sich
 * hart nachweisen statt behaupten:
 *
 * 1. **Die vorausberechnete Größe muss auf das Byte stimmen.** Sie geht als
 *    `Content-Length` an den Browser; weicht sie ab, bricht der Download ab.
 * 2. **Ein fremdes Programm muss das Archiv lesen können.** Dafür wird jedes
 *    erzeugte Archiv mit PHPs `ZipArchive` (also libzip) geöffnet und jede
 *    Datei daraus mit dem erwarteten Inhalt verglichen. libzip prüft dabei von
 *    sich aus die CRC-32 jedes Eintrags — eine falsche Prüfsumme fiele sofort
 *    auf.
 *
 * Damit wirklich der Weg getestet wird, den auch der Webserver nimmt, gibt
 * `ZipStream::send()` unmittelbar nach STDOUT aus. Das Werkzeug ruft sich
 * deshalb je Fall selbst als Unterprozess auf und leitet dessen Ausgabe in eine
 * Datei um.
 *
 * Aufruf:
 *
 *   C:\xampp\php\php.exe tools/zipprobe.php
 *
 * @license LGPL-3.0-or-later
 */

use Schachbulle\ContaoPhotoalbumsBundle\Download\ZipStream;

require __DIR__.'/../src/Download/ZipStream.php';

/**
 * Legt die Dateien eines Prüffalls an und sagt, was im Archiv zu erwarten ist.
 *
 * Die Funktion läuft in beiden Prozessen — im Unterprozess, der das Archiv
 * schreibt, und im Hauptprozess, der es prüft. Sie muss deshalb bei gleichem
 * Fallnamen immer dasselbe liefern; alle Inhalte werden aus dem Namen und einer
 * laufenden Nummer erzeugt, nichts ist zufällig.
 *
 * @param string $strCase Der Name des Prüffalls
 * @param string $strDir  Das Verzeichnis für die Testdateien
 *
 * @return array<string, string> Erwarteter Archivinhalt: Name im Archiv => Inhalt
 */
function baueFall(string $strCase, string $strDir): array
{
	if (!is_dir($strDir))
	{
		mkdir($strDir, 0777, true);
	}

	$arrExpected = array();

	switch ($strCase)
	{
		// Der Regelfall: ein paar Dateien und die Infodatei
		case 'klein':
			foreach (array('eins', 'zwei', 'drei') as $i => $strName)
			{
				$strContent = str_repeat("Foto $strName\n", 10);
				file_put_contents($strDir.'/'.$strName.'.jpg', $strContent);
				$arrExpected['album/'.$strName.'.jpg'] = $strContent;
			}

			$arrExpected['album/album.txt'] = "Infotext mit Umlauten: äöüß\r\n";
			break;

		// Mehrere Blöcke je Datei: der Lesevorgang muss über die Blockgrenze tragen
		case 'gross':
			$strContent = str_repeat('0123456789abcdef', 98304); // 1,5 MiB
			file_put_contents($strDir.'/gross.bin', $strContent);
			$arrExpected['album/gross.bin'] = $strContent;
			break;

		// Gleiche Dateinamen aus verschiedenen Ordnern dürfen einander nicht überschreiben
		case 'doppelt':
			for ($i = 1; $i <= 3; ++$i)
			{
				if (!is_dir($strDir.'/ordner'.$i))
				{
					mkdir($strDir.'/ordner'.$i, 0777, true);
				}

				$strContent = "Inhalt aus Ordner $i\n";
				file_put_contents($strDir.'/ordner'.$i.'/bild.jpg', $strContent);
			}

			$arrExpected['album/bild.jpg'] = "Inhalt aus Ordner 1\n";
			$arrExpected['album/bild_2.jpg'] = "Inhalt aus Ordner 2\n";
			$arrExpected['album/bild_3.jpg'] = "Inhalt aus Ordner 3\n";
			break;

		// Dateiname mit Umlauten: das UTF-8-Kennzeichen muss gesetzt sein
		case 'umlaut':
			$strContent = "Schöne Grüße\n";
			file_put_contents($strDir.'/Übergröße Straße.jpg', $strContent);
			$arrExpected['album/Übergröße Straße.jpg'] = $strContent;
			break;

		// Änderungszeit vor 1980: das ZIP-Datumsfeld kann das nicht ausdrücken
		case 'alt':
			$strContent = "Olympiade 1968\n";
			file_put_contents($strDir.'/olympiade.jpg', $strContent);
			touch($strDir.'/olympiade.jpg', -38106000);
			$arrExpected['album/olympiade.jpg'] = $strContent;
			break;

		// Der eigentliche Auftrag: mehr als tausend Dateien in einem Archiv
		case 'viele':
			for ($i = 1; $i <= 1200; ++$i)
			{
				$strName = sprintf('foto%04d.jpg', $i);
				$strContent = str_repeat("Bild $i ", 64);
				file_put_contents($strDir.'/'.$strName, $strContent);
				$arrExpected['album/'.$strName] = $strContent;
			}
			break;

		// Eine Datei verschwindet zwischen Anmeldung und Ausgabe
		case 'fehlt':
			file_put_contents($strDir.'/da.jpg', "vorhanden\n");
			$arrExpected['album/da.jpg'] = "vorhanden\n";
			break;

		/*
		 * Eine Datei schrumpft, nachdem ihre Größe für die Vorausberechnung
		 * ermittelt wurde. Das Archiv muss trotzdem genau so lang werden wie
		 * angekündigt — sonst verschöbe sich alles Folgende und der Browser
		 * bräche den ganzen Download ab. Die fehlenden Bytes werden mit Null
		 * aufgefüllt: Eine Datei ist dann unbrauchbar, alle anderen bleiben heil.
		 */
		case 'schrumpf':
			file_put_contents($strDir.'/foto.jpg', str_repeat('X', 50000));
			$arrExpected['album/foto.jpg'] = str_repeat('X', 100).str_repeat("\0", 49900);
			break;
	}

	return $arrExpected;
}

/**
 * Befüllt den Schreiber mit den Dateien eines Prüffalls.
 *
 * @param string $strCase Der Name des Prüffalls
 * @param string $strDir  Das Verzeichnis mit den Testdateien
 *
 * @return ZipStream Der fertig befüllte Schreiber
 */
function baueArchiv(string $strCase, string $strDir): ZipStream
{
	$objZip = new ZipStream();

	switch ($strCase)
	{
		case 'klein':
			foreach (array('eins', 'zwei', 'drei') as $strName)
			{
				$objZip->addFile($strDir.'/'.$strName.'.jpg', 'album/'.$strName.'.jpg');
			}

			$objZip->addText("Infotext mit Umlauten: äöüß\r\n", 'album/album.txt', time());
			break;

		case 'gross':
			$objZip->addFile($strDir.'/gross.bin', 'album/gross.bin');
			break;

		case 'doppelt':
			for ($i = 1; $i <= 3; ++$i)
			{
				$objZip->addFile($strDir.'/ordner'.$i.'/bild.jpg', 'album/bild.jpg');
			}
			break;

		case 'umlaut':
			$objZip->addFile($strDir.'/Übergröße Straße.jpg', 'album/Übergröße Straße.jpg');
			break;

		case 'alt':
			$objZip->addFile($strDir.'/olympiade.jpg', 'album/olympiade.jpg');
			break;

		case 'viele':
			for ($i = 1; $i <= 1200; ++$i)
			{
				$strName = sprintf('foto%04d.jpg', $i);
				$objZip->addFile($strDir.'/'.$strName, 'album/'.$strName);
			}
			break;

		case 'fehlt':
			$objZip->addFile($strDir.'/gibtesnicht.jpg', 'album/gibtesnicht.jpg');
			$objZip->addFile($strDir.'/da.jpg', 'album/da.jpg');
			break;

		case 'schrumpf':
			$objZip->addFile($strDir.'/foto.jpg', 'album/foto.jpg');

			// Erst nach dem Anmelden kürzen: Die Größe ist da schon gemerkt
			file_put_contents($strDir.'/foto.jpg', str_repeat('X', 100));
			break;
	}

	return $objZip;
}

$strDir = sys_get_temp_dir().'/pa2-zipprobe';

/*
 * Der Unterprozess schreibt nur das Archiv und sonst gar nichts: Jedes weitere
 * Byte auf STDOUT machte es unbrauchbar.
 */
if (isset($argv[1]) && '--emit' === $argv[1])
{
	$strCase = (string) ($argv[2] ?? '');

	/*
	 * Meldungen müssen auf STDERR: Eine einzige Warnung auf STDOUT stünde
	 * mitten im Archiv und machte es unlesbar. Im Betrieb gilt dasselbe —
	 * deshalb darf `display_errors` auf einer Produktivinstallation nicht an
	 * sein, was Contao im prod-Modus von sich aus sicherstellt.
	 */
	ini_set('display_errors', 'stderr');

	/*
	 * Die Testdateien werden auch hier angelegt, nicht nur im Hauptlauf: Der
	 * Fall „schrumpf“ kürzt eine Datei absichtlich, und der Unterprozess muss
	 * mit derselben Ausgangslage beginnen wie die Vorausberechnung.
	 */
	baueFall($strCase, $strDir.'/'.$strCase);

	baueArchiv($strCase, $strDir.'/'.$strCase)->send();

	/*
	 * Der Spitzenspeicher geht auf STDERR — er ist der Nachweis, dass wirklich
	 * gestreamt und nicht gesammelt wird. STDOUT gehört allein dem Archiv.
	 */
	fwrite(STDERR, 'PEAK:'.memory_get_peak_usage(true)."\n");

	exit(0);
}

$intErrors = 0;
$intChecks = 0;

/**
 * Meldet das Ergebnis einer Prüfung.
 *
 * @param string $strLabel Was geprüft wurde
 * @param bool   $blnOk    Ergebnis
 * @param string $strHint  Zusatzangabe im Fehlerfall
 *
 * @return void
 */
function pruefe(string $strLabel, bool $blnOk, string $strHint = ''): void
{
	global $intErrors, $intChecks;

	++$intChecks;

	if ($blnOk)
	{
		echo '  [ok]   '.$strLabel."\n";

		return;
	}

	++$intErrors;
	echo '  [FEHL] '.$strLabel.('' !== $strHint ? ' — '.$strHint : '')."\n";
}

/**
 * Löscht ein Verzeichnis samt Inhalt.
 *
 * @param string $strPath Das Verzeichnis
 *
 * @return void
 */
function raeumeAuf(string $strPath): void
{
	if (!is_dir($strPath))
	{
		return;
	}

	foreach (scandir($strPath) as $strEntry)
	{
		if ('.' === $strEntry || '..' === $strEntry)
		{
			continue;
		}

		$strFull = $strPath.'/'.$strEntry;

		is_dir($strFull) ? raeumeAuf($strFull) : unlink($strFull);
	}

	rmdir($strPath);
}

echo "Prüfwerkzeug ZIP-Schreiber\n";
echo 'PHP: '.PHP_VERSION.'    libzip: '.(\defined('ZipArchive::LIBZIP_VERSION') ? ZipArchive::LIBZIP_VERSION : phpversion('zip'))."\n\n";

if (!class_exists('ZipArchive'))
{
	fwrite(STDERR, "Die Erweiterung zip fehlt; ohne sie lässt sich das Ergebnis nicht gegenprüfen.\n");

	exit(1);
}

raeumeAuf($strDir);

$arrCases = array(
	'klein' => 'Drei Dateien und ein Text',
	'gross' => 'Eine Datei über mehrere Blöcke',
	'doppelt' => 'Gleiche Namen aus drei Ordnern',
	'umlaut' => 'Umlaute im Dateinamen',
	'alt' => 'Änderungszeit von 1968',
	'viele' => '1200 Dateien in einem Archiv',
	'fehlt' => 'Eine Datei gibt es nicht',
	'schrumpf' => 'Datei schrumpft nach der Anmeldung',
);

foreach ($arrCases as $strCase => $strTitle)
{
	echo "\n".$strTitle." (".$strCase.")\n";

	$strCaseDir = $strDir.'/'.$strCase;
	$arrExpected = baueFall($strCase, $strCaseDir);

	// Dieselbe Rechnung wie im Unterprozess, nur ohne Ausgabe
	$intExpectedSize = baueArchiv($strCase, $strCaseDir)->getSize();

	$strZipFile = $strDir.'/'.$strCase.'.zip';

	$strErrFile = $strDir.'/'.$strCase.'.err';

	$strCommand = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --emit '.escapeshellarg($strCase)
		.' > '.escapeshellarg($strZipFile).' 2> '.escapeshellarg($strErrFile);

	$arrOutput = array();
	exec($strCommand, $arrOutput, $intStatus);

	pruefe('Unterprozess beendet sich sauber', 0 === $intStatus, 'Status '.$intStatus);

	/*
	 * Der Spitzenspeicher des Unterprozesses ist der Nachweis, dass wirklich
	 * gestreamt wird: Er darf nicht mit der Größe des Archivs wachsen. Die
	 * Grenze ist großzügig gewählt, denn sie enthält PHP selbst; entscheidend
	 * ist, dass der Fall mit 1200 Dateien nicht mehr braucht als der mit drei.
	 */
	$strErr = is_file($strErrFile) ? (string) file_get_contents($strErrFile) : '';
	$intPeak = preg_match('/PEAK:(\d+)/', $strErr, $arrMatch) ? (int) $arrMatch[1] : 0;

	pruefe(
		sprintf('Spitzenspeicher unter 8 MiB (gemessen: %s)', $intPeak > 0 ? round($intPeak / 1048576, 1).' MiB' : 'nicht gemessen'),
		$intPeak > 0 && $intPeak < 8388608
	);

	if (!is_file($strZipFile))
	{
		pruefe('Archiv erzeugt', false, 'keine Datei');

		continue;
	}

	$intRealSize = (int) filesize($strZipFile);

	/*
	 * Die wichtigste Prüfung des ganzen Werkzeugs: Stimmt die Vorausberechnung
	 * nicht, geht ein falsches Content-Length an den Browser, und der bricht
	 * den Download als beschädigt ab.
	 */
	pruefe(
		'Vorausberechnete Größe stimmt auf das Byte',
		$intExpectedSize === $intRealSize,
		'berechnet '.$intExpectedSize.', geschrieben '.$intRealSize
	);

	$objZip = new ZipArchive();
	$varOpen = $objZip->open($strZipFile, ZipArchive::CHECKCONS);

	if (true !== $varOpen)
	{
		pruefe('ZipArchive öffnet das Archiv', false, 'Fehlercode '.$varOpen);

		continue;
	}

	pruefe('ZipArchive öffnet das Archiv', true);
	pruefe('Anzahl der Einträge', \count($arrExpected) === $objZip->numFiles, $objZip->numFiles.' statt '.\count($arrExpected));

	$intWrong = 0;
	$arrMissing = array();

	foreach ($arrExpected as $strName => $strContent)
	{
		$varActual = $objZip->getFromName($strName);

		if (false === $varActual)
		{
			$arrMissing[] = $strName;

			continue;
		}

		if ($varActual !== $strContent)
		{
			++$intWrong;
		}
	}

	pruefe('Alle erwarteten Namen vorhanden', empty($arrMissing), implode(', ', \array_slice($arrMissing, 0, 3)));

	/*
	 * getFromName() entpackt und prüft dabei die CRC-32. Stimmen alle Inhalte,
	 * sind damit auch alle Prüfsummen richtig berechnet.
	 */
	pruefe('Alle Inhalte samt Prüfsumme richtig', 0 === $intWrong, $intWrong.' Abweichungen');

	$objZip->close();
}

raeumeAuf($strDir);

echo "\n";
echo $intErrors > 0
	? "ERGEBNIS: $intErrors von $intChecks Prüfungen fehlgeschlagen.\n"
	: "ERGEBNIS: alle $intChecks Prüfungen bestanden.\n";

exit($intErrors > 0 ? 1 : 0);
