<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Modules;

use Schachbulle\ContaoPhotoalbumsBundle\Helper\Assets;

/**
 * Frontend-Modul „Fotoalbum Leser“.
 *
 * Zeigt ausschliesslich die Fotos eines Albums. Ist kein Album in der Adresse
 * angegeben, geht es zurueck zur eingestellten Alben-Uebersicht.
 */
class ModulePhotoalbums2View extends ModulePhotoalbums2
{
	/**
	 * Kennung der Ansicht.
	 *
	 * @var string
	 */
	protected $strPa2Type = 'MOD_VIEW';

	/**
	 * Schluessel der Modulbezeichnung fuer den Platzhalter im Backend.
	 *
	 * @var string
	 */
	protected $strPa2Key = 'photoalbums2view';

	/**
	 * Dieses Modul arbeitet immer mit getrennten Seiten.
	 *
	 * @return string Immer `pa2_with_detail_page`
	 */
	protected function getPa2Mode(): string
	{
		return 'pa2_with_detail_page';
	}

	/**
	 * Nimmt die Foto-Ansicht-Seite aus den Einstellungen.
	 *
	 * Dieses Modul *ist* die Foto-Ansicht; eine Weiterleitung auf eine andere
	 * Seite ergaebe hier keinen Sinn.
	 *
	 * @return void
	 */
	protected function adjustSettings(): void
	{
		$this->pa2DetailPage = '';
	}

	/**
	 * Zeigt die Fotos oder leitet zur Alben-Uebersicht zurueck.
	 *
	 * @return void
	 */
	protected function compile()
	{
		global $objPage;

		Assets::addFrontendCss();

		$varAlbum = self::getAlbumParameter();
		$intPageId = null !== $objPage ? (int) $objPage->id : 0;

		if (null !== $varAlbum)
		{
			$this->prepareImages();

			return;
		}

		if (is_numeric($this->pa2OverviewPage) && $this->pa2OverviewPage > 0 && $intPageId !== (int) $this->pa2OverviewPage)
		{
			$this->goToOverviewPage();

			return;
		}

		$this->goToRootPage();
	}
}
