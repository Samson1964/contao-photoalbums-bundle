<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Download;

use Contao\Config;
use Contao\Date;
use Contao\FilesModel;
use Contao\StringUtil;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Runtime;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Packt die Dateien eines Albums in ein Archiv und liefert es aus.
 *
 * Die Klasse entscheidet **nicht**, wer herunterladen darf. Das hat der
 * Aufrufer bereits geklärt: Übergeben wird ein Album, das die Auswahl von
 * {@see \Schachbulle\ContaoPhotoalbumsBundle\Album\Album} überstanden hat und
 * damit veröffentlicht, freigegeben und im Zeitfilter des Moduls ist. So gibt
 * es für den Download keine zweite Zugriffsregel, die von der Anzeige
 * abweichen könnte.
 *
 * Ausgegeben wird als {@see StreamedResponse}: Contao schickt die Kopfzeilen,
 * und {@see ZipStream} schreibt das Archiv danach unmittelbar in die Ausgabe.
 * Ein Album mit tausend Fotos braucht deshalb weder Platz auf der Platte noch
 * nennenswert Speicher.
 */
class AlbumArchive
{
	/**
	 * Name der Infodatei im Archiv.
	 *
	 * @var string
	 */
	public const INFO_FILE = 'album.txt';

	/**
	 * Der Albumdatensatz.
	 *
	 * @var object
	 */
	private $objAlbum;

	/**
	 * Die Dateien des Albums in der Reihenfolge der Anzeige.
	 *
	 * @var array<int, string>
	 */
	private $arrUuids;

	/**
	 * @param object             $objAlbum Der Albumdatensatz, wie ihn
	 *                                     `Album::getAlbums()` liefert
	 * @param array<int, string> $arrUuids Die sortierten Datei-UUIDs; leer
	 *                                     übergeben heißt, sie aus dem
	 *                                     Datensatz zu nehmen
	 */
	public function __construct($objAlbum, array $arrUuids = array())
	{
		$this->objAlbum = $objAlbum;
		$this->arrUuids = !empty($arrUuids)
			? $arrUuids
			: (array) ($objAlbum->arrSortedImageUuids ?? array());
	}

	/**
	 * Baut die Antwort, die das Archiv ausliefert.
	 *
	 * Die Kopfzeilen sind vollständig gesetzt, bevor das erste Byte fließt —
	 * insbesondere `Content-Length`, das {@see ZipStream::getSize()} vorab
	 * ausrechnet. Der Browser kann damit Fortschritt und Restdauer anzeigen,
	 * was bei einem Album von mehreren Gigabyte den Unterschied zwischen
	 * „lädt“ und „hängt“ ausmacht.
	 *
	 * @return Response|null Die Antwort oder null, wenn das Album keine
	 *                       einzige lesbare Datei enthält
	 */
	public function getResponse(): ?Response
	{
		$objZip = new ZipStream();
		$strFolder = $this->getBaseName();

		foreach ($this->arrUuids as $uuid)
		{
			$objFile = FilesModel::findByUuid($uuid);

			if (null === $objFile || 'file' !== $objFile->type)
			{
				continue;
			}

			$objZip->addFile(
				Runtime::getProjectDir().'/'.$objFile->path,
				$strFolder.'/'.$objFile->name
			);
		}

		if ($objZip->count() < 1)
		{
			return null;
		}

		$objZip->addText($this->getInfoText($objZip->count()), $strFolder.'/'.self::INFO_FILE, time());

		$objResponse = new StreamedResponse(
			static function () use ($objZip): void
			{
				/*
				 * Die Sitzung muss vor dem Streamen geschlossen werden. PHP
				 * hält ihre Datei sonst gesperrt, und der Besucher könnte,
				 * solange sein Album lädt, keine einzige weitere Seite
				 * aufrufen — bei einem Archiv von mehreren Gigabyte wäre die
				 * ganze Website für ihn minutenlang blockiert.
				 */
				if (PHP_SESSION_ACTIVE === session_status())
				{
					session_write_close();
				}

				// Ein Album mit tausend Fotos braucht länger als die übliche Begrenzung
				if (\function_exists('set_time_limit'))
				{
					@set_time_limit(0);
				}

				$objZip->send();
			}
		);

		$objResponse->headers->set('Content-Type', 'application/zip');
		$objResponse->headers->set('Content-Length', (string) $objZip->getSize());
		$objResponse->headers->set('Content-Transfer-Encoding', 'binary');

		$objResponse->headers->set(
			'Content-Disposition',
			HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $strFolder.'.zip', $this->getFallbackName())
		);

		/*
		 * Ein Archiv gehört in keinen Zwischenspeicher: Es kann geschützt sein,
		 * es ist groß, und es ändert sich, sobald jemand ein Foto hinzufügt.
		 */
		$objResponse->headers->set('Cache-Control', 'private, no-store, must-revalidate');
		$objResponse->headers->set('Pragma', 'no-cache');

		// nginx würde die Antwort sonst puffern und den Download verzögern
		$objResponse->headers->set('X-Accel-Buffering', 'no');

		// Zweite Bremse gegen Suchmaschinen; die erste ist das `nofollow` am Verweis
		$objResponse->headers->set('X-Robots-Tag', 'noindex, nofollow');

		return $objResponse;
	}

	/**
	 * Liefert den Namen für Archivdatei und Ordner darin.
	 *
	 * Grundlage ist der Alias des Albums; er ist bereits auf unbedenkliche
	 * Zeichen beschränkt. Fehlt er, tritt die Albumnummer an seine Stelle.
	 *
	 * @return string Der Name ohne Endung
	 */
	private function getBaseName(): string
	{
		$strName = (string) ($this->objAlbum->alias ?? '');
		$strName = preg_replace('/[^A-Za-z0-9_-]/', '', $strName);

		if ('' === (string) $strName)
		{
			$strName = 'album-'.(int) ($this->objAlbum->id ?? 0);
		}

		return (string) $strName;
	}

	/**
	 * Liefert einen reinen ASCII-Namen für ältere Browser.
	 *
	 * `Content-Disposition` nennt den Namen zweimal: einmal in UTF-8 für die
	 * heutigen Browser und einmal als Rückfallebene in ASCII.
	 *
	 * @return string Der Name der Archivdatei mit Endung
	 */
	private function getFallbackName(): string
	{
		return $this->getBaseName().'.zip';
	}

	/**
	 * Stellt die Angaben des Albums als Textdatei zusammen.
	 *
	 * Sie beantwortet später die Frage, woher ein Ordner voller Fotos stammt —
	 * eine Frage, die sich erfahrungsgemäß erst Jahre danach stellt, wenn
	 * niemand mehr weiß, welche Website den Ordner geliefert hat.
	 *
	 * Geschrieben wird mit einer Bytefolgemarkierung und Wagenrücklauf: So
	 * zeigt auch der Windows-Editor die Umlaute richtig und bricht die Zeilen
	 * um.
	 *
	 * @param int $intFiles Anzahl der Dateien im Archiv, ohne diese Infodatei
	 *
	 * @return string Der fertige Inhalt der Datei
	 */
	private function getInfoText(int $intFiles): string
	{
		$arrLang = $GLOBALS['TL_LANG']['PA2']['downloadInfo'] ?? array();

		$strTitle = strip_tags((string) ($this->objAlbum->title ?? ''));

		$arrLines = array();
		$arrLines[] = $strTitle;
		// Zeichen zählen, nicht Bytes: „ß“ und Umlaute belegen in UTF-8 zwei
		$arrLines[] = str_repeat('=', max(3, \function_exists('mb_strlen') ? mb_strlen($strTitle, 'UTF-8') : \strlen($strTitle)));
		$arrLines[] = '';

		$arrFields = array(
			'date' => $this->getDateRange(),
			'event' => $this->cleanText((string) ($this->objAlbum->event ?? '')),
			'place' => $this->cleanText((string) ($this->objAlbum->place ?? '')),
			'photographer' => $this->cleanText((string) ($this->objAlbum->photographer ?? '')),
			'files' => (string) $intFiles,
		);

		foreach ($arrFields as $strKey => $strValue)
		{
			if ('' === $strValue)
			{
				continue;
			}

			$arrLines[] = sprintf('%-16s %s', ($arrLang[$strKey] ?? $strKey).':', $strValue);
		}

		$strDescription = $this->cleanText((string) ($this->objAlbum->description ?? ''));

		if ('' !== $strDescription)
		{
			$arrLines[] = '';
			$arrLines[] = ($arrLang['description'] ?? 'description').':';
			$arrLines[] = $strDescription;
		}

		$arrLines[] = '';
		global $objPage;

		$strDatimFormat = (string) ((null !== $objPage && '' !== (string) $objPage->datimFormat) ? $objPage->datimFormat : Config::get('datimFormat'));

		$arrLines[] = sprintf(
			$arrLang['source'] ?? 'Heruntergeladen am %s von %s',
			Date::parse($strDatimFormat),
			Runtime::getBaseUrl()
		);

		return "\xEF\xBB\xBF".implode("\r\n", $arrLines)."\r\n";
	}

	/**
	 * Setzt Start- und Enddatum des Albums zu einer Angabe zusammen.
	 *
	 * Die Prüfung auf eine leere Zeichenkette statt auf einen Wert größer null
	 * ist Absicht: Aufnahmedaten vor 1970 sind negative Zeitstempel und
	 * verschwänden sonst.
	 *
	 * @return string Datum oder Zeitraum; leer, wenn kein Datum hinterlegt ist
	 */
	private function getDateRange(): string
	{
		global $objPage;

		// Dasselbe Format wie in der Anzeige: bevorzugt das der Seite
		$strFormat = (string) ((null !== $objPage && '' !== (string) $objPage->dateFormat) ? $objPage->dateFormat : Config::get('dateFormat'));

		$strStart = (string) ($this->objAlbum->startdate ?? '');
		$strEnd = (string) ($this->objAlbum->enddate ?? '');

		if ('' === $strStart)
		{
			return '';
		}

		$strDate = Date::parse($strFormat, (int) $strStart);

		if ('' !== $strEnd && $strEnd !== $strStart)
		{
			$strDate .= ' - '.Date::parse($strFormat, (int) $strEnd);
		}

		return $strDate;
	}

	/**
	 * Macht aus einem Feld des Albums reinen Text.
	 *
	 * Die Beschreibung kommt aus einem Editor und trägt Markup; Absätze und
	 * Zeilenumbrüche werden zu Zeilenwechseln, alles andere fällt weg.
	 *
	 * @param string $strValue Der rohe Feldwert
	 *
	 * @return string Der Text ohne Markup, mit Wagenrücklauf als Zeilenende
	 */
	private function cleanText(string $strValue): string
	{
		$strValue = preg_replace('#</p>|<br[^>]*>#i', "\n", $strValue);
		$strValue = strip_tags((string) $strValue);
		$strValue = StringUtil::decodeEntities($strValue);
		$strValue = str_replace("\xC2\xA0", ' ', $strValue);
		$strValue = preg_replace("/[ \t]+/", ' ', $strValue);
		$strValue = preg_replace("/\r\n|\r|\n/", "\r\n", (string) $strValue);
		$strValue = preg_replace("/(\r\n){3,}/", "\r\n\r\n", (string) $strValue);

		return trim((string) $strValue);
	}
}
