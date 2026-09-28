<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Feed;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Feed;
use Contao\FeedItem;
use Contao\File;
use Contao\StringUtil;
use Contao\System;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Runtime;
use Schachbulle\ContaoPhotoalbumsBundle\Model\ArchiveModel;
use Schachbulle\ContaoPhotoalbumsBundle\Routing\AlbumUrlResolver;

/**
 * Erzeugt die RSS- beziehungsweise Atom-Dateien der Fotoalben-Archive.
 *
 * Die Dateien liegen unter `<Webverzeichnis>/share/<alias>.xml` — an derselben
 * Stelle, an der auch Contao 4 seine Nachrichten- und Kalenderfeeds ablegt.
 *
 * Aufgerufen wird die Klasse täglich über den Cron-Auftrag und außerdem aus
 * den Datenbereichen heraus, sobald ein Archiv oder ein Album geändert wurde.
 * Die früher benutzte Registrierung über `$GLOBALS['TL_CRON']` gibt es unter
 * Contao 5 nicht mehr; das Attribut `AsCronJob` liegt dagegen in beiden
 * Fassungen am selben Ort.
 */
#[AsCronJob('daily')]
class FeedGenerator
{
	/**
	 * Das Contao-Rahmenwerk, damit der Cron-Lauf die Alt-Klassen benutzen darf.
	 *
	 * @var ContaoFramework
	 */
	private $framework;

	/**
	 * @param ContaoFramework $framework Wird per Autowiring gesetzt
	 */
	public function __construct(ContaoFramework $framework)
	{
		$this->framework = $framework;
	}

	/**
	 * Einstiegspunkt des täglichen Cron-Auftrags.
	 *
	 * @return void
	 */
	public function __invoke(): void
	{
		$this->framework->initialize();
		$this->generateFeeds();
	}

	/**
	 * Erzeugt die Dateien aller Archive, die einen Feed haben sollen.
	 *
	 * Geschützte Archive bleiben außen vor: Ein Feed wäre öffentlich
	 * lesbar und würde den Zugriffsschutz aushebeln.
	 *
	 * @return void
	 */
	public function generateFeeds(): void
	{
		$objArchives = ArchiveModel::findBy(array('makeFeed=?', 'protected!=?'), array('1', '1'));

		if (null === $objArchives)
		{
			return;
		}

		while ($objArchives->next())
		{
			$this->generateFiles($objArchives->current());
		}
	}

	/**
	 * Erzeugt oder löscht die Datei eines einzelnen Archivs.
	 *
	 * @param int $intId Datensatznummer des Archivs
	 *
	 * @return void Ohne passendes Archiv geschieht nichts
	 */
	public function generateFeed(int $intId): void
	{
		$objArchive = ArchiveModel::findByPk($intId);

		if (null === $objArchive)
		{
			return;
		}

		// Kein Feed gewünscht oder Archiv geschützt: vorhandene Datei entfernen
		if (!$objArchive->makeFeed || $objArchive->protected)
		{
			$this->deleteFile($this->getFeedName($objArchive));

			return;
		}

		$this->generateFiles($objArchive);
	}

	/**
	 * Schreibt die XML-Datei eines Archivs.
	 *
	 * @param ArchiveModel $objArchive Der Archivdatensatz
	 *
	 * Die Verweise auf die Alben bildet der {@see AlbumUrlResolver} — derselbe,
	 * den auch Link-Picker und Insert-Tags benutzen. Ist am Archiv eine Seite
	 * eingetragen, gewinnt sie wie bisher; fehlt sie, wird die Seite aus der
	 * Einbindung der Module ermittelt. Ein Album, das keine Seite zeigt, fehlt
	 * im Feed: Ein Eintrag ohne gültigen Verweis wäre für Feedleser nutzlos.
	 *
	 * @return void Ohne ein einziges verlinkbares Album geschieht nichts, damit
	 *              eine vorhandene Datei nicht durch einen leeren Feed ersetzt wird
	 */
	private function generateFiles(ArchiveModel $objArchive): void
	{
		$objResolver = new AlbumUrlResolver();
		$arrAlbums = $this->findAlbums($objArchive);
		$arrLinks = array();

		foreach ($arrAlbums as $arrAlbum)
		{
			$strUrl = $objResolver->generate((object) $arrAlbum);

			if ('' !== $strUrl)
			{
				$arrLinks[(int) $arrAlbum['id']] = $strUrl;
			}
		}

		if (empty($arrLinks))
		{
			return;
		}

		$strFeedName = $this->getFeedName($objArchive);
		$strBase = '' !== (string) $objArchive->feedBase ? (string) $objArchive->feedBase : Runtime::getBaseUrl();

		$objFeed = new Feed($strFeedName);
		$objFeed->link = $strBase;
		$objFeed->title = $objArchive->title;
		$objFeed->description = $objArchive->description;
		$objFeed->language = $objArchive->language;
		$objFeed->published = $objArchive->tstamp;

		foreach ($arrAlbums as $arrAlbum)
		{
			if (!isset($arrLinks[(int) $arrAlbum['id']]))
			{
				continue;
			}

			$objItem = new FeedItem();
			$objItem->title = $arrAlbum['title'];
			$objItem->link = $this->buildAbsoluteUrl($strBase, $arrLinks[(int) $arrAlbum['id']]);
			$objItem->published = (int) $arrAlbum['startdate'];
			$objItem->author = $arrAlbum['authorName'];
			$objItem->description = Runtime::replaceInsertTags((string) $arrAlbum['description']);

			$objFeed->addItem($objItem);
		}

		$strMethod = 'atom' === $objArchive->format ? 'generateAtom' : 'generateRss';

		File::putContent(
			$this->getSharePath().'/'.$strFeedName.'.xml',
			Runtime::replaceInsertTags($objFeed->$strMethod())
		);
	}

	/**
	 * Liest die veröffentlichten Alben eines Archivs.
	 *
	 * Die Abfrage läuft bewusst über die Datenbankklasse statt über das
	 * Modell, weil zusätzlich der Name des Autors aus `tl_user` gebraucht wird.
	 *
	 * @param ArchiveModel $objArchive Der Archivdatensatz
	 *
	 * @return array<int, array<string, mixed>> Die Alben in Sortierreihenfolge
	 */
	private function findAlbums(ArchiveModel $objArchive): array
	{
		$time = time();

		$objStatement = Database::getInstance()->prepare(
			"SELECT p.*, (SELECT name FROM tl_user u WHERE u.id=p.author) AS authorName
			 FROM tl_photoalbums2_album p
			 WHERE p.pid=? AND (p.start='' OR p.start<$time) AND (p.stop='' OR p.stop>$time) AND p.published='1'
			 ORDER BY p.sorting ASC"
		);

		if ($objArchive->maxItems > 0)
		{
			$objStatement->limit((int) $objArchive->maxItems);
		}

		$objResult = $objStatement->execute($objArchive->id);

		return $objResult->fetchAllAssoc();
	}

	/**
	 * Bildet den Dateinamen des Feeds ohne Endung.
	 *
	 * @param ArchiveModel $objArchive Der Archivdatensatz
	 *
	 * @return string Der Alias des Archivs oder ersatzweise `pa2<Nummer>`
	 */
	private function getFeedName(ArchiveModel $objArchive): string
	{
		$strAlias = (string) $objArchive->alias;

		return '' !== $strAlias ? $strAlias : 'pa2'.$objArchive->id;
	}

	/**
	 * Liefert das Ausgabeverzeichnis der Feeds, projektrelativ.
	 *
	 * Das Webverzeichnis heißt je nach Installation `public` oder `web`; der
	 * Container-Parameter `contao.web_dir` kennt den richtigen Namen und ist in
	 * beiden Contao-Fassungen gesetzt.
	 *
	 * @return string Etwa `public/share`
	 */
	private function getSharePath(): string
	{
		$strWebDir = (string) System::getContainer()->getParameter('contao.web_dir');

		return StringUtil::stripRootDir($strWebDir).'/share';
	}

	/**
	 * Löscht eine vorhandene Feed-Datei.
	 *
	 * @param string $strFeedName Dateiname ohne Endung
	 *
	 * @return void Fehlt die Datei, geschieht nichts
	 */
	private function deleteFile(string $strFeedName): void
	{
		$strPath = $this->getSharePath().'/'.$strFeedName.'.xml';

		if (is_file(Runtime::getProjectDir().'/'.$strPath))
		{
			$objFile = new File($strPath);
			$objFile->delete();
		}
	}

	/**
	 * Setzt Basisadresse und Seitenadresse zu einer vollständigen Adresse zusammen.
	 *
	 * @param string $strBase Die Basisadresse mit Protokoll
	 * @param string $strUrl  Das Ergebnis von PageModel::getFrontendUrl()
	 *
	 * @return string Die vollständige Adresse; ist sie schon vollständig,
	 *                bleibt sie unverändert
	 */
	private function buildAbsoluteUrl(string $strBase, string $strUrl): string
	{
		if (preg_match('#^https?://#i', $strUrl))
		{
			return $strUrl;
		}

		return rtrim($strBase, '/').'/'.ltrim($strUrl, '/');
	}
}
