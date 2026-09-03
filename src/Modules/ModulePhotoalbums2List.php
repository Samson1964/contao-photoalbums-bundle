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
 * Frontend-Modul „Fotoalben Liste“.
 *
 * Zeigt ausschliesslich die Alben-Uebersicht. Wird ein Album gewaehlt, leitet
 * das Modul auf die eingestellte Foto-Ansicht-Seite um — es gibt hier also
 * bewusst keinen Weg, die Fotos auf derselben Seite darzustellen.
 */
class ModulePhotoalbums2List extends ModulePhotoalbums2
{
	/**
	 * Kennung der Ansicht.
	 *
	 * @var string
	 */
	protected $strPa2Type = 'MOD_LIST';

	/**
	 * Schluessel der Modulbezeichnung fuer den Platzhalter im Backend.
	 *
	 * @var string
	 */
	protected $strPa2Key = 'photoalbums2list';

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
	 * Nimmt die Uebersichtsseite aus den Einstellungen.
	 *
	 * Dieses Modul *ist* die Uebersicht; ein Rueckverweis auf eine andere
	 * Uebersichtsseite ergaebe hier keinen Sinn.
	 *
	 * @return void
	 */
	protected function adjustSettings(): void
	{
		$this->pa2OverviewPage = '';
	}

	/**
	 * Zeigt die Alben-Uebersicht oder leitet zur Foto-Ansicht weiter.
	 *
	 * @return void
	 */
	protected function compile()
	{
		global $objPage;

		Assets::addFrontendCss();

		$varAlbum = self::getAlbumParameter();
		$intPageId = null !== $objPage ? (int) $objPage->id : 0;

		if (null === $varAlbum && ('' === (string) $this->pa2DetailPage || $this->pa2DetailPage != $intPageId))
		{
			$this->prepareAlbums();

			return;
		}

		if (null !== $varAlbum)
		{
			$this->goToDetailPage();

			return;
		}

		$this->goToRootPage();
	}
}
