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
use Contao\PageModel;
use Contao\StringUtil;
use Schachbulle\ContaoPhotoalbumsBundle\Album\Album;
use Schachbulle\ContaoPhotoalbumsBundle\Album\Image;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Pagination;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Runtime;

/**
 * Baut die Foto-Ansicht eines einzelnen Albums.
 *
 * Ausgegeben werden die Fotos der aktuellen Seite als Kacheln; alle uebrigen
 * Fotos des Albums stehen zusaetzlich als unsichtbare Verweise im Markup,
 * damit die Lightbox das ganze Album kennt und der Besucher darin blaettern
 * kann, ohne die Seite zu wechseln.
 */
class ImageViewParser extends ViewParser
{
	/**
	 * Die Nummer des anzuzeigenden Albums, sofern fest vorgegeben.
	 *
	 * @var int
	 */
	private $intAlbumId = 0;

	/**
	 * Der Albumdatensatz.
	 *
	 * @var object|null
	 */
	private $objAlbum;

	/**
	 * Die Foto-UUIDs der aktuellen Seite.
	 *
	 * @var array<int, string>
	 */
	private $arrItems = array();

	/**
	 * Die Foto-UUIDs des gesamten Albums.
	 *
	 * @var array<int, string>
	 */
	private $arrAllItems = array();

	/**
	 * @param object $objTemplate Das Template des Moduls oder Inhaltselements
	 * @param mixed  $intAlbumId  Albumnummer, wenn sie fest im Inhaltselement
	 *                            steht; sonst 0, dann wird sie der Adresse
	 *                            entnommen
	 */
	public function __construct($objTemplate, $intAlbumId = 0)
	{
		if (is_numeric($intAlbumId) && $intAlbumId > 0)
		{
			$this->intAlbumId = (int) $intAlbumId;
		}

		parent::__construct($objTemplate);
	}

	/**
	 * Uebernimmt die Moduleinstellungen in die Arbeitsvariablen des Templates.
	 *
	 * @return void
	 */
	protected function generate(): void
	{
		$this->Template->intMaxItems = $this->Template->pa2NumberOfImages;
		$this->Template->intItemsPerPage = $this->Template->pa2ImagesPerPage;
		$this->Template->intItemsPerRow = $this->Template->pa2ImagesPerRow;
		$this->Template->strTemplate = '' !== (string) $this->Template->pa2ImageViewTemplate ? $this->Template->pa2ImageViewTemplate : 'pa2_wrap';
		$this->Template->strSubtemplate = '' !== (string) $this->Template->pa2ImagesTemplate ? $this->Template->pa2ImagesTemplate : 'pa2_image';
		$this->Template->showMetaDescriptions = $this->Template->pa2ImagesShowMetaDescriptions;
		$this->Template->arrMetaFields = $this->Template->pa2ImagesMetaFields;

		$this->Template->size = $this->Template->pa2ImagesImageSize;
		$this->Template->imagemargin = $this->Template->pa2ImagesImageMargin;

		$this->Template->showTitle = $this->Template->pa2ImagesShowTitle;
		$this->Template->teaser = $this->cleanRteOutput($this->Template->pa2Teaser);
		$this->Template->showHeadline = '' !== (string) $this->Template->headline ? $this->Template->pa2ImagesShowHeadline : false;
		$this->Template->showTeaser = '' !== (string) $this->Template->teaser ? $this->Template->pa2ImagesShowTeaser : false;

		parent::generate();
	}

	/**
	 * Holt das Album und baut die Fotokacheln.
	 *
	 * @return void
	 */
	protected function compile(): void
	{
		global $objPage;

		// Das Modul-Template durch das gewaehlte Rahmen-Template ersetzen
		$objTemplate = new FrontendTemplate($this->Template->strTemplate);
		$objTemplate->setData($this->Template->getData());
		$this->Template = $objTemplate;

		$objAlbumList = new Album($this->getAlbumIdOrAlias(), $this->Template->getData());
		$objAlbums = $objAlbumList->getAlbums();

		if (null === $objAlbums || $objAlbums->count() < 1)
		{
			$this->setEmptyTemplate($GLOBALS['TL_LANG']['MSC']['albumNotFound'] ?? '');

			return;
		}

		$objAlbum = $objAlbums->current();

		if (empty($objAlbum->arrSortedImageUuids))
		{
			$this->setEmptyTemplate($GLOBALS['TL_LANG']['MSC']['imagesNotFound'] ?? '');

			return;
		}

		// Seitentitel, Seitenbeschreibung und Kommentare nur dort setzen, wo das
		// Album wirklich der Hauptinhalt der Seite ist
		if (Runtime::isFrontend() && \in_array($this->Template->pa2type, array('MOD', 'MOD_VIEW'), true))
		{
			if (null !== $objPage && '' !== (string) $objAlbum->title)
			{
				$objPage->pageTitle = strip_tags(StringUtil::stripInsertTags((string) $objAlbum->title));
			}

			if (null !== $objPage && '' !== (string) $objAlbum->description)
			{
				$objPage->description = $this->prepareDescription($objAlbum->description);
			}

			$this->addComments($objAlbum);
		}

		$this->objAlbum = $objAlbum;
		$this->arrAllItems = $objAlbum->arrSortedImageUuids;

		$objPagination = new Pagination($this->arrAllItems, $this->Template->intMaxItems, $this->Template->intItemsPerPage);

		$this->arrItems = $objPagination->getItems();
		$this->Template->pagination = $objPagination->getPagination();
		$this->Template->totalItems = $objPagination->getTotalItems();

		$this->parseImages();
	}

	/**
	 * Ermittelt, welches Album anzuzeigen ist.
	 *
	 * Vorrang hat die fest eingestellte Albumnummer des Inhaltselements. Sonst
	 * wird der Parameter `album` aus der Adresse gelesen; fehlt er, greift das
	 * namenlose Anhaengsel `auto_item`. Beide Wege werden gebraucht, weil sich
	 * unter Contao 4.13 einstellen laesst, welche Adressform erzeugt wird.
	 *
	 * @return mixed Albumnummer, Alias oder 0, wenn nichts angegeben ist
	 */
	private function getAlbumIdOrAlias()
	{
		if ($this->intAlbumId > 0)
		{
			return $this->intAlbumId;
		}

		$varValue = Input::get('album');

		if (null === $varValue || '' === $varValue)
		{
			$varValue = Input::get('auto_item');
		}

		return (null === $varValue || '' === $varValue) ? 0 : $varValue;
	}

	/**
	 * Erzeugt zu jedem Foto ein Teil-Template.
	 *
	 * Durchlaufen werden **alle** Fotos des Albums, nicht nur die der aktuellen
	 * Seite: Die uebrigen kommen als unsichtbare Verweise ins Markup, damit die
	 * Lightbox das ganze Album kennt. Sie tragen ein leeres Ein-Punkt-Bild,
	 * laden also nichts nach.
	 *
	 * @return void
	 */
	private function parseImages(): void
	{
		if (null === $this->objAlbum)
		{
			$this->setEmptyTemplate($GLOBALS['TL_LANG']['MSC']['albumNotFound'] ?? '');

			return;
		}

		if (empty($this->arrItems))
		{
			$this->setEmptyTemplate($GLOBALS['TL_LANG']['MSC']['imagesNotFound'] ?? '');

			return;
		}

		$objAlbum = $this->objAlbum;

		$this->Template->title = strip_tags((string) $objAlbum->title);
		$this->Template->alt = strip_tags((string) $objAlbum->title);
		$this->Template->showTitle = '' !== (string) $this->Template->title ? $this->Template->showTitle : false;

		$strCssClass = (string) ($this->Template->cssClass ?? '');
		$strAlbumClass = (string) ($objAlbum->cssClass ?? '');

		if ('' !== $strAlbumClass)
		{
			$this->Template->cssClass = '' === $strCssClass ? $strAlbumClass : $strCssClass.' '.$strAlbumClass;
		}

		$this->Template->event = $objAlbum->event;
		$this->Template->place = $objAlbum->place;
		$this->Template->photographer = $objAlbum->photographer;
		$this->Template->description = $objAlbum->description;
		$this->Template->numberOfAllImages = \count($this->arrAllItems);

		$this->generateBacklink();

		$this->Template = $this->addDateToTemplate($this->Template, $objAlbum->startdate, $objAlbum->enddate);
		$this->Template = $this->addMetaFieldsToTemplate($this->Template);

		$arrItems = array();
		$strIndividualId = $this->generateIndividualId();
		$i = 0;

		foreach ($this->arrAllItems as $uuid)
		{
			$objImage = new Image($uuid);
			$objFile = $objImage->getFile();

			if (null === $objFile)
			{
				continue;
			}

			$objSubtemplate = new FrontendTemplate($this->Template->strSubtemplate);
			$objSubtemplate->setData($this->Template->getData());

			$objSubtemplate->title = $this->getImageTitle($objFile);
			$objSubtemplate->alt = $this->getImageTitle($objFile);
			$objSubtemplate->show = false;
			$objSubtemplate->elementID = $i;
			$objSubtemplate->albumID = $objAlbum->id.'_'.$strIndividualId;
			$objSubtemplate->href = str_replace(' ', '%20', (string) $objFile->path);

			if (\in_array($uuid, $this->arrItems))
			{
				$objSubtemplate = $objImage->addToTemplate($objSubtemplate, StringUtil::deserialize($objSubtemplate->arrImage, true));
				$objSubtemplate = $this->addSpecificClassesToTemplate($objSubtemplate, $i);
				$objSubtemplate->show = true;

				++$i;
			}
			else
			{
				// Foto einer anderen Seite: nur der Verweis, kein geladenes Bild
				$objImage->addBlankToTemplate($objSubtemplate);
			}

			$arrItems[] = $objSubtemplate->parse();
		}

		$this->Template->items = $arrItems;
	}

	/**
	 * Baut den Rueckverweis auf die Alben-Uebersicht.
	 *
	 * Ziel ist die im Modul eingestellte Uebersichtsseite; ist keine
	 * eingestellt, wird die Seite genommen, von der der Besucher gekommen ist.
	 * Deren Nummer und die Seitenzahl der Blaetterliste hat die Alben-Uebersicht
	 * beim Aufbau in der Sitzung hinterlegt.
	 *
	 * @return void
	 */
	private function generateBacklink(): void
	{
		if (!Runtime::isFrontend() || 'CE' === $this->Template->pa2type)
		{
			return;
		}

		global $objPage;

		$objSession = Runtime::getSession();

		$intPageNumber = null !== $objSession ? $objSession->get('pa2PageNumber_'.$this->Template->id) : null;
		$intPageId = null !== $objSession ? $objSession->get('pa2PageId_'.$this->Template->id) : null;

		if (is_numeric($this->Template->pa2OverviewPage) && $this->Template->pa2OverviewPage > 0)
		{
			$intPageId = $this->Template->pa2OverviewPage;
		}

		$intPageNumber = is_numeric($intPageNumber) ? (int) $intPageNumber : 1;
		$intPageId = is_numeric($intPageId) ? (int) $intPageId : (null !== $objPage ? (int) $objPage->id : 0);

		$objTargetPage = PageModel::findWithDetails($intPageId);

		if (null === $objTargetPage)
		{
			return;
		}

		$strReferer = $objTargetPage->getFrontendUrl();
		$strReferer .= $intPageNumber > 1 ? '?page='.$intPageNumber : '';

		$this->Template->referer = $strReferer;
		$this->Template->back = $GLOBALS['TL_LANG']['PA2']['goBack'] ?? '';
	}
}
