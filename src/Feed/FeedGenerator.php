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
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Runtime;
use Schachbulle\ContaoPhotoalbumsBundle\Model\ArchiveModel;

/**
 * Erzeugt die RSS- beziehungsweise Atom-Dateien der Fotoalben-Archive.
 *
 * Die Dateien liegen unter `<Webverzeichnis>/share/<alias>.xml` — an derselben
 * Stelle, an der auch Contao 4 seine Nachrichten- und Kalenderfeeds ablegt.
 *
 * Aufgerufen wird die Klasse taeglich ueber den Cron-Auftrag und ausserdem aus
 * den Datenbereichen heraus, sobald ein Archiv oder ein Album geaendert wurde.
 * Die frueher benutzte Registrierung ueber `$GLOBALS['TL_CRON']` gibt es unter
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
	 * Einstiegspunkt des taeglichen Cron-Auftrags.
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
	 * Geschuetzte Archive bleiben aussen vor: Ein Feed waere oeffentlich
	 * lesbar und wuerde den Zugriffsschutz aushebeln.
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
	 * Erzeugt oder loescht die Datei eines einzelnen Archivs.
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

		// Kein Feed gewuenscht oder Archiv geschuetzt: vorhandene Datei entfernen
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
	 * @return void Ohne hinterlegte Modulseite geschieht nichts, weil sich
	 *              dann keine Verweise auf die Alben bilden lassen
	 */
	private function generateFiles(ArchiveModel $objArchive): void
	{
		$objTargetPage = PageModel::findWithDetails((int) $objArchive->modulePage);

		if (null === $objTargetPage)
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

		foreach ($this->findAlbums($objArchive) as $arrAlbum)
		{
			$strAlias = '' !== (string) $arrAlbum['alias'] ? $arrAlbum['alias'] : $arrAlbum['id'];
			$strParams = Runtime::useAutoItem() ? '/'.$strAlias : '/album/'.$strAlias;

			$objItem = new FeedItem();
			$objItem->title = $arrAlbum['title'];
			$objItem->link = $this->buildAbsoluteUrl($strBase, $objTargetPage->getFrontendUrl($strParams));
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
	 * Liest die veroeffentlichten Alben eines Archivs.
	 *
	 * Die Abfrage laeuft bewusst ueber die Datenbankklasse statt ueber das
	 * Modell, weil zusaetzlich der Name des Autors aus `tl_user` gebraucht wird.
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
	 * Das Webverzeichnis heisst je nach Installation `public` oder `web`; der
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
	 * Loescht eine vorhandene Feed-Datei.
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
	 * Setzt Basisadresse und Seitenadresse zu einer vollstaendigen Adresse zusammen.
	 *
	 * @param string $strBase Die Basisadresse mit Protokoll
	 * @param string $strUrl  Das Ergebnis von PageModel::getFrontendUrl()
	 *
	 * @return string Die vollstaendige Adresse; ist sie schon vollstaendig,
	 *                bleibt sie unveraendert
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
