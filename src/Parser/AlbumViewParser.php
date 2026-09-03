<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Parser;

use Contao\FrontendTemplate;
use Contao\Input;
use Contao\Model\Collection;
use Contao\StringUtil;
use Schachbulle\ContaoPhotoalbumsBundle\Album\Album;
use Schachbulle\ContaoPhotoalbumsBundle\Album\Archive;
use Schachbulle\ContaoPhotoalbumsBundle\Album\Image;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Pagination;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\PreviewImage;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Runtime;
use Schachbulle\ContaoPhotoalbumsBundle\Sorter\ImageSorter;

/**
 * Baut die Alben-Uebersicht.
 *
 * Ausgegeben wird eine Kachelliste der Alben eines oder mehrerer Archive, je
 * Album mit Vorschaubild, Titel und den ausgewaehlten Meta-Angaben. Wahlweise
 * traegt jede Kachel schon alle Fotos des Albums als versteckte Lightbox-Gruppe
 * mit sich — dann braucht es gar keine Detailseite.
 */
class AlbumViewParser extends ViewParser
{
	/**
	 * Die Alben dieser Seite.
	 *
	 * @var Collection|null
	 */
	private $objAlbums;

	/**
	 * Uebernimmt die Moduleinstellungen in die Arbeitsvariablen des Templates.
	 *
	 * @return void
	 */
	protected function generate(): void
	{
		$this->Template->intMaxItems = $this->Template->pa2NumberOfAlbums;
		$this->Template->intItemsPerPage = $this->Template->pa2AlbumsPerPage;
		$this->Template->intItemsPerRow = $this->Template->pa2AlbumsPerRow;
		$this->Template->strTemplate = '' !== (string) $this->Template->pa2AlbumViewTemplate ? $this->Template->pa2AlbumViewTemplate : 'pa2_wrap';
		$this->Template->strSubtemplate = '' !== (string) $this->Template->pa2AlbumsTemplate ? $this->Template->pa2AlbumsTemplate : 'pa2_album';
		$this->Template->intDetailPage = $this->Template->pa2DetailPage;
		$this->Template->albumLightbox = $this->Template->pa2AlbumLightbox;
		$this->Template->showMetaDescriptions = $this->Template->pa2AlbumsShowMetaDescriptions;
		$this->Template->arrMetaFields = $this->Template->pa2AlbumsMetaFields;

		$this->Template->size = $this->Template->pa2AlbumsImageSize;
		$this->Template->imagemargin = $this->Template->pa2AlbumsImageMargin;

		$this->Template->showTitle = $this->Template->pa2AlbumsShowTitle;
		$this->Template->teaser = $this->cleanRteOutput($this->Template->pa2Teaser);
		$this->Template->showHeadline = '' !== (string) $this->Template->headline ? $this->Template->pa2AlbumsShowHeadline : false;
		$this->Template->showTeaser = '' !== (string) $this->Template->teaser ? $this->Template->pa2AlbumsShowTeaser : false;

		parent::generate();
	}

	/**
	 * Holt die Alben und baut die Kacheln.
	 *
	 * @return void
	 */
	protected function compile(): void
	{
		// Das Modul-Template durch das gewaehlte Rahmen-Template ersetzen
		$objTemplate = new FrontendTemplate($this->Template->strTemplate);
		$objTemplate->setData($this->Template->getData());
		$this->Template = $objTemplate;

		$objArchive = new Archive(
			StringUtil::deserialize($this->Template->pa2Archives, true),
			$this->Template->getData()
		);

		$arrAllAlbums = $objArchive->getAlbumIds();

		if (empty($arrAllAlbums))
		{
			$this->setEmptyTemplate($GLOBALS['TL_LANG']['MSC']['albumsNotFound'] ?? '');

			return;
		}

		$objPagination = new Pagination($arrAllAlbums, $this->Template->intMaxItems, $this->Template->intItemsPerPage);

		$this->Template->pagination = $objPagination->getPagination();
		$this->Template->totalItems = $objPagination->getTotalItems();

		$objAlbumList = new Album($objPagination->getItems(), $this->Template->getData());
		$this->objAlbums = $objAlbumList->getAlbums();

		$this->parseAlbums();
	}

	/**
	 * Erzeugt zu jedem Album ein Teil-Template.
	 *
	 * Nebenbei werden Seitennummer und Seitennummer-Kennung in der Sitzung
	 * abgelegt; die Foto-Ansicht baut daraus spaeter den Rueckverweis auf genau
	 * die Uebersichtsseite, von der der Besucher gekommen ist.
	 *
	 * @return void
	 */
	private function parseAlbums(): void
	{
		if (null === $this->objAlbums || $this->objAlbums->count() < 1)
		{
			$this->setEmptyTemplate($GLOBALS['TL_LANG']['MSC']['albumsNotFound'] ?? '');

			return;
		}

		global $objPage;

		$objSession = Runtime::getSession();

		if (null !== $objSession && null !== $objPage)
		{
			$intPage = (int) Input::get('page');
			$objSession->set('pa2PageNumber_'.$this->Template->id, $intPage > 0 ? $intPage : 1);
			$objSession->set('pa2PageId_'.$this->Template->id, $objPage->id);
		}

		$arrItems = array();
		$objAlbums = $this->objAlbums;
		$i = 0;

		while ($objAlbums->next())
		{
			$objSubtemplate = new FrontendTemplate($this->Template->strSubtemplate);
			$objSubtemplate->setData($this->Template->getData());

			$objSubtemplate->title = strip_tags((string) $objAlbums->title);
			$objSubtemplate->alt = strip_tags((string) $objAlbums->title);
			$objSubtemplate->showTitle = '' !== (string) $objSubtemplate->title ? $objSubtemplate->showTitle : false;
			$objSubtemplate->event = $objAlbums->event;
			$objSubtemplate->place = $objAlbums->place;
			$objSubtemplate->photographer = $objAlbums->photographer;
			$objSubtemplate->description = $objAlbums->description;
			$objSubtemplate->numberOfAllImages = \count($objAlbums->arrSortedImageUuids ?? array());

			$objSubtemplate = $this->addDateToTemplate($objSubtemplate, $objAlbums->startdate, $objAlbums->enddate);
			$objSubtemplate = $this->addSpecificClassesToTemplate($objSubtemplate, $i);
			$objSubtemplate = $this->addLinkToTemplate($objSubtemplate, $objAlbums->current());
			$objSubtemplate = $this->addMetaFieldsToTemplate($objSubtemplate);

			$objPreviewImage = new PreviewImage($objAlbums->current(), $objSubtemplate->pa2PreviewImage);
			$objImage = new Image($objPreviewImage->getPreviewImageUuid());
			$objImage->addToTemplate($objSubtemplate);

			// Die im Album hinterlegte CSS-Klasse an die berechneten anhaengen
			$strClass = (string) ($objSubtemplate->class ?? '');
			$strAlbumClass = (string) ($objAlbums->cssClass ?? '');

			if ('' !== $strAlbumClass)
			{
				$objSubtemplate->class = '' === $strClass ? $strAlbumClass : $strClass.' '.$strAlbumClass;
			}

			$objSubtemplate = $this->albumLightbox($objSubtemplate, $objAlbums->current());

			$arrItems[] = $objSubtemplate->parse();

			++$i;
		}

		$this->Template->items = $arrItems;
	}

	/**
	 * Haengt alle Fotos eines Albums als versteckte Lightbox-Gruppe an.
	 *
	 * Das erste Foto wird zum Ziel des Kachel-Verweises; alle weiteren stehen
	 * als unsichtbare Verweise im Markup, damit die Lightbox sie kennt, ohne
	 * dass der Browser sie beim Seitenaufbau laedt.
	 *
	 * @param object $objTemplate Das Teil-Template der Kachel
	 * @param object $objAlbum    Der Albumdatensatz
	 *
	 * @return object Dasselbe Template, gegebenenfalls mit `albumID` und
	 *                `albumLightboxImages`
	 */
	private function albumLightbox($objTemplate, $objAlbum)
	{
		if (!$objTemplate->albumLightbox)
		{
			return $objTemplate;
		}

		$objTemplate->albumID = $objAlbum->id.'_'.$this->generateIndividualId();

		$objImageSorter = new ImageSorter($objAlbum->imageSortType, $objAlbum->images, $objAlbum->imageSort);
		$arrUuids = $objImageSorter->getSortedUuids();

		if (empty($arrUuids))
		{
			return $objTemplate;
		}

		$arrLightboxImages = array();
		$i = 0;

		foreach ($arrUuids as $uuid)
		{
			$objImage = new Image($uuid);
			$objFile = $objImage->getFile();

			if (null === $objFile)
			{
				continue;
			}

			if (0 === $i)
			{
				// Das erste Foto wird das Ziel des Kachel-Verweises
				$objTemplate->href = str_replace(' ', '%20', (string) $objFile->path);
			}
			else
			{
				$objImageTemplate = new FrontendTemplate('pa2_lightbox_image');
				$objImageTemplate->albumID = $objTemplate->albumID;
				$objImageTemplate->href = str_replace(' ', '%20', (string) $objFile->path);
				$objImageTemplate->title = basename((string) $objFile->path);
				$objImageTemplate->alt = $this->getImageTitle($objFile);

				$objImage->addBlankToTemplate($objImageTemplate);

				$arrLightboxImages[] = $objImageTemplate->parse();
			}

			++$i;
		}

		$objTemplate->albumLightboxImages = $arrLightboxImages;

		return $objTemplate;
	}
}
