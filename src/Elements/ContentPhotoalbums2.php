<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Elements;

use Contao\ContentElement;
use Contao\StringUtil;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Assets;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Runtime;
use Schachbulle\ContaoPhotoalbumsBundle\Parser\ImageViewParser;

/**
 * Inhaltselement „Fotoalbum“.
 *
 * Anders als die Frontend-Module zeigt das Inhaltselement immer die Fotos
 * **eines fest ausgewaehlten** Albums; die Adresse spielt keine Rolle. Damit
 * laesst sich ein Album mitten in einen Artikel setzen.
 *
 * Die Registrierung erfolgt ueber `$GLOBALS['TL_CTE']` mit dem vollen
 * Klassennamen; das funktioniert unter Contao 4.13 wie unter Contao 5
 * (`ContentElement::findClass()`).
 */
class ContentPhotoalbums2 extends ContentElement
{
	/**
	 * Das Rahmen-Template.
	 *
	 * @var string
	 */
	protected $strTemplate = 'pa2_wrap';

	/**
	 * Das Teil-Template je Foto.
	 *
	 * @var string
	 */
	protected $strSubtemplate = 'pa2_image';

	/**
	 * Bereitet die Elementdaten auf und erzeugt die Ausgabe.
	 *
	 * Im Backend werden Ueberschrift, Titel und Teaser ausgeblendet und die
	 * Bilder auf Daumennagelgroesse gesetzt: Dort dient die Ausgabe nur der
	 * Wiedererkennung des Elements.
	 *
	 * @return string Das fertige Markup des Inhaltselements
	 */
	public function generate()
	{
		$this->pa2type = 'CE';

		$this->groups = StringUtil::deserialize($this->groups);
		$this->pa2ImagesMetaFields = StringUtil::deserialize($this->pa2ImagesMetaFields);
		$this->pa2TimeFilterStart = StringUtil::deserialize($this->pa2TimeFilterStart);
		$this->pa2TimeFilterEnd = StringUtil::deserialize($this->pa2TimeFilterEnd);

		$this->pa2ImagesShowHeadline = 1 == $this->pa2ImagesShowHeadline;
		$this->pa2ImagesShowTitle = 1 == $this->pa2ImagesShowTitle;
		$this->pa2ImagesShowTeaser = 1 == $this->pa2ImagesShowTeaser;

		if (Runtime::isBackend())
		{
			$this->pa2ImagesShowHeadline = false;
			$this->pa2ImagesShowTitle = false;
			$this->pa2ImagesShowTeaser = false;
			$this->pa2ImagesPerRow = 1;
			$this->pa2ImagesPerPage = 0;
			$this->pa2NumberOfImages = 0;
			$this->pa2ImagesImageSize = serialize(array(50, 31, 'center_center'));
			$this->pa2ImagesImageMargin = serialize(array(
				'bottom' => 6,
				'left'   => '',
				'right'  => 6,
				'top'    => '',
				'unit'   => 'px',
			));
		}

		return parent::generate();
	}

	/**
	 * Baut die Foto-Ansicht des ausgewaehlten Albums.
	 *
	 * @return void
	 */
	protected function compile()
	{
		Assets::addFrontendCss();

		$objParser = new ImageViewParser($this->Template, $this->pa2Album);
		$this->Template = $objParser->getViewParserTemplate();
	}
}
