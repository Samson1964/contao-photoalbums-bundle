<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Modules;

use Contao\BackendTemplate;
use Contao\Input;
use Contao\Module;
use Contao\PageModel;
use Contao\StringUtil;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Assets;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Runtime;
use Schachbulle\ContaoPhotoalbumsBundle\Parser\AlbumViewParser;
use Schachbulle\ContaoPhotoalbumsBundle\Parser\ImageViewParser;

/**
 * Frontend-Modul „Fotoalbum“.
 *
 * Das Modul kann beides: die Uebersicht der Alben und die Fotos eines
 * einzelnen Albums. Welche der beiden Ansichten erscheint, haengt vom
 * eingestellten Modus und davon ab, ob in der Adresse ein Album steht.
 *
 * Die Registrierung erfolgt ueber `$GLOBALS['FE_MOD']` mit dem vollen
 * Klassennamen. Das funktioniert unter Contao 4.13 wie unter Contao 5
 * (`Module::findClass()`), ohne dass das Modul zu einem Fragment-Controller
 * umgebaut werden muesste.
 */
class ModulePhotoalbums2 extends Module
{
	/**
	 * Das Rahmen-Template.
	 *
	 * @var string
	 */
	protected $strTemplate = 'pa2_wrap';

	/**
	 * Das Teil-Template je Eintrag.
	 *
	 * @var string
	 */
	protected $strSubtemplate = 'pa2_album';

	/**
	 * Kennung der Ansicht, die dieses Modul liefert.
	 *
	 * Die Foto-Ansicht wertet sie aus, um zu entscheiden, ob sie Seitentitel
	 * und Seitenbeschreibung ueberschreiben darf. Die Ableitungen setzen hier
	 * `MOD_LIST` beziehungsweise `MOD_VIEW`.
	 *
	 * @var string
	 */
	protected $strPa2Type = 'MOD';

	/**
	 * Schluessel der Modulbezeichnung fuer den Platzhalter im Backend.
	 *
	 * @var string
	 */
	protected $strPa2Key = 'photoalbums2';

	/**
	 * Bereitet die Moduldaten auf und erzeugt die Ausgabe.
	 *
	 * Im Backend erscheint statt der Alben der uebliche Platzhalter, damit die
	 * Modulliste uebersichtlich bleibt und keine Bilder erzeugt werden.
	 *
	 * @return string Das fertige Markup des Moduls
	 */
	public function generate()
	{
		if (Runtime::isBackend())
		{
			$objTemplate = new BackendTemplate('be_wildcard');
			$objTemplate->wildcard = '### '.strtoupper($GLOBALS['TL_LANG']['FMD'][$this->strPa2Key][0] ?? 'PHOTOALBUMS').' ###';

			return $objTemplate->parse();
		}

		$this->pa2type = $this->strPa2Type;

		$this->groups = StringUtil::deserialize($this->groups);
		$this->pa2Archives = StringUtil::deserialize($this->pa2Archives);
		$this->pa2AlbumSort = StringUtil::deserialize($this->pa2AlbumSort);
		$this->pa2AlbumsMetaFields = StringUtil::deserialize($this->pa2AlbumsMetaFields);
		$this->pa2ImagesMetaFields = StringUtil::deserialize($this->pa2ImagesMetaFields);
		$this->pa2TimeFilterStart = StringUtil::deserialize($this->pa2TimeFilterStart);
		$this->pa2TimeFilterEnd = StringUtil::deserialize($this->pa2TimeFilterEnd);

		$this->pa2ImagesShowHeadline = 1 == $this->pa2ImagesShowHeadline;
		$this->pa2ImagesShowTitle = 1 == $this->pa2ImagesShowTitle;
		$this->pa2ImagesShowTeaser = 1 == $this->pa2ImagesShowTeaser;
		$this->pa2AlbumsShowHeadline = 1 == $this->pa2AlbumsShowHeadline;
		$this->pa2AlbumsShowTitle = 1 == $this->pa2AlbumsShowTitle;
		$this->pa2AlbumsShowTeaser = 1 == $this->pa2AlbumsShowTeaser;

		$strMode = $this->getPa2Mode();

		$this->pa2AlbumLightbox = 'pa2_only_album_view' === $strMode;
		$this->pa2DetailPage = 'pa2_with_detail_page' === $strMode ? $this->pa2DetailPage : '';

		$this->adjustSettings();

		return parent::generate();
	}

	/**
	 * Liefert den Ansichtsmodus des Moduls.
	 *
	 * Die Ableitungen „Liste“ und „Leser“ haben das Feld `pa2Mode` gar nicht in
	 * ihrer Palette; sie arbeiten immer mit getrennten Seiten und ueberschreiben
	 * diese Methode entsprechend. So haengt ihr Verhalten nicht davon ab, ob in
	 * der Datenbank zufaellig der richtige Modus steht.
	 *
	 * @return string Einer der Werte `pa2_on_one_page`, `pa2_only_album_view`
	 *                oder `pa2_with_detail_page`
	 */
	protected function getPa2Mode(): string
	{
		return (string) $this->pa2Mode;
	}

	/**
	 * Haken fuer die Ableitungen, um Einstellungen zu uebersteuern.
	 *
	 * Der Aufruf erfolgt, nachdem alle Felder aufbereitet sind, aber bevor
	 * Contao das Template erzeugt und `compile()` aufruft. Die Basisfassung tut
	 * nichts.
	 *
	 * @return void
	 */
	protected function adjustSettings(): void
	{
	}

	/**
	 * Entscheidet, welche Ansicht gebaut oder wohin umgeleitet wird.
	 *
	 * @return void
	 */
	protected function compile()
	{
		global $objPage;

		Assets::addFrontendCss();

		$varAlbum = self::getAlbumParameter();
		$intPageId = null !== $objPage ? (int) $objPage->id : 0;

		// Fotos eines Albums zeigen
		if (null !== $varAlbum && ('' === (string) $this->pa2DetailPage || $this->isDetailPage($intPageId)))
		{
			$this->prepareImages();

			return;
		}

		// Alben-Uebersicht zeigen
		if (null === $varAlbum && ('' === (string) $this->pa2DetailPage || $this->pa2DetailPage != $intPageId))
		{
			$this->prepareAlbums();

			return;
		}

		// Album gewaehlt, aber die Fotos gehoeren auf eine andere Seite
		if (null !== $varAlbum)
		{
			$this->goToDetailPage();

			return;
		}

		// Uebersichtsseite anspringen, sonst die Startseite
		if (is_numeric($this->pa2OverviewPage) && $this->pa2OverviewPage > 0 && $intPageId !== (int) $this->pa2OverviewPage)
		{
			$this->goToOverviewPage();

			return;
		}

		$this->goToRootPage();
	}

	/**
	 * Liest das gewaehlte Album aus der Adresse.
	 *
	 * Gelesen werden beide moeglichen Formen: das benannte Paar
	 * `/album/albumalias` und das namenlose Anhaengsel `/albumalias`. Contao
	 * 4.13 erzeugt je nach Systemeinstellung die eine oder die andere; unter
	 * Contao 5 gibt es nur noch das Anhaengsel.
	 *
	 * @return string|null Der Wert aus der Adresse oder null, wenn kein Album
	 *                     angegeben wurde
	 */
	protected static function getAlbumParameter(): ?string
	{
		$varValue = Input::get('album');

		if (null === $varValue || '' === $varValue)
		{
			$varValue = Input::get('auto_item');
		}

		return (null === $varValue || '' === $varValue) ? null : (string) $varValue;
	}

	/**
	 * Prueft, ob die aktuelle Seite die eingestellte Foto-Ansicht-Seite ist.
	 *
	 * Beruecksichtigt wird auch die Uebersetzung einer Seite: Steht das Modul
	 * auf der deutschen Detailseite und wird die englische aufgerufen, verweist
	 * deren `languageMain` auf die deutsche.
	 *
	 * @param int $intPageId Nummer der aktuell aufgerufenen Seite
	 *
	 * @return bool true, wenn die Fotos hier ausgegeben werden sollen
	 */
	private function isDetailPage(int $intPageId): bool
	{
		global $objPage;

		if ((int) $this->pa2DetailPage === $intPageId)
		{
			return true;
		}

		return null !== $objPage
			&& '' !== (string) $objPage->languageMain
			&& (int) $objPage->languageMain === (int) $this->pa2DetailPage;
	}

	/**
	 * Baut die Foto-Ansicht.
	 *
	 * @return void
	 */
	protected function prepareImages(): void
	{
		$objParser = new ImageViewParser($this->Template);
		$this->Template = $objParser->getViewParserTemplate();
	}

	/**
	 * Baut die Alben-Uebersicht.
	 *
	 * @return void
	 */
	protected function prepareAlbums(): void
	{
		$objParser = new AlbumViewParser($this->Template);
		$this->Template = $objParser->getViewParserTemplate();
	}

	/**
	 * Leitet auf die Seite mit der Foto-Ansicht um.
	 *
	 * Eine gesetzte Seitenzahl wird mitgenommen, damit der Besucher beim
	 * Zurueckgehen wieder auf derselben Seite der Blaetterliste landet.
	 *
	 * @return void Die Methode kehrt nur zurueck, wenn gar nicht umgeleitet
	 *              werden muss; sonst beendet die Umleitung den Aufruf
	 */
	public function goToDetailPage(): void
	{
		global $objPage;

		if (null !== $objPage && (int) $objPage->id === (int) $this->pa2DetailPage)
		{
			return;
		}

		$objDetailPage = PageModel::findWithDetails((int) $this->pa2DetailPage);

		if (null === $objDetailPage)
		{
			return;
		}

		$strAlbum = (string) self::getAlbumParameter();
		$strParams = Runtime::useAutoItem() ? '/'.$strAlbum : '/album/'.$strAlbum;
		$strUrl = $objDetailPage->getFrontendUrl($strParams);

		$intPage = (int) Input::get('page');

		if ($intPage > 0)
		{
			$strUrl .= '?page='.$intPage;
		}

		$this->redirect($strUrl);
	}

	/**
	 * Leitet auf die Seite mit der Alben-Uebersicht um.
	 *
	 * @return void
	 */
	public function goToOverviewPage(): void
	{
		global $objPage;

		if (null !== $objPage && (int) $objPage->id === (int) $this->pa2OverviewPage)
		{
			return;
		}

		$objRedirectPage = PageModel::findWithDetails((int) $this->pa2OverviewPage);

		if (null === $objRedirectPage)
		{
			return;
		}

		$this->redirect($objRedirectPage->getFrontendUrl());
	}

	/**
	 * Leitet auf die Startseite um.
	 *
	 * Kommt zum Zug, wenn weder ein Album angegeben ist noch eine
	 * Uebersichtsseite eingestellt wurde. Die Seite wird vorher aus Index und
	 * Cache genommen, damit die Umleitung nicht zwischengespeichert wird.
	 *
	 * @return void
	 */
	public function goToRootPage(): void
	{
		global $objPage;

		if (null === $objPage)
		{
			return;
		}

		$objPage->noSearch = 1;
		$objPage->cache = 0;

		$objRootPage = PageModel::findWithDetails((int) $objPage->rootId);

		if (null === $objRootPage)
		{
			return;
		}

		$this->redirect($objRootPage->getFrontendUrl());
	}
}
