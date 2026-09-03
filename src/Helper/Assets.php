<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Helper;

use Contao\LayoutModel;

/**
 * Bindet die Stilvorlagen und Skripte des Bundles ein.
 *
 * Die Dateien liegen unter `src/Resources/public` und sind im Frontend wie im
 * Backend ueber `bundles/contaophotoalbums/` erreichbar. Die frueher benutzte
 * Konstante `TL_FILES_URL` gibt es unter Contao 5 nicht mehr; ein relativer
 * Pfad tut hier dasselbe.
 */
class Assets
{
	/**
	 * Oeffentlicher Pfad der Bundle-Dateien.
	 *
	 * @var string
	 */
	public const PATH = 'bundles/contaophotoalbums/';

	/**
	 * Bindet das Frontend-Stylesheet ein.
	 *
	 * Im Seitenlayout laesst sich das mit dem Feld `skipPhotoalbums2`
	 * abschalten, wenn das Theme die Alben selbst gestaltet.
	 *
	 * @return void
	 */
	public static function addFrontendCss(): void
	{
		global $objPage;

		if (null !== $objPage)
		{
			$objLayout = LayoutModel::findByPk($objPage->layout);

			if (null !== $objLayout && $objLayout->skipPhotoalbums2)
			{
				return;
			}
		}

		$strFile = self::PATH.'photoalbums.css';

		if (!\in_array($strFile, $GLOBALS['TL_CSS'] ?? array(), true))
		{
			$GLOBALS['TL_CSS'][] = $strFile;
		}
	}

	/**
	 * Bindet Stilvorlage und Skript des Sortier-Assistenten im Backend ein.
	 *
	 * Beide Assistenten (Fotos und Alben) teilen sich dieselben Dateien. Die
	 * Pruefung auf ein bereits eingetragenes Vorkommen ist noetig, weil eine
	 * Eingabemaske mehrere Assistenten enthalten kann.
	 *
	 * @return void
	 */
	public static function addSortWizardAssets(): void
	{
		$strCss = self::PATH.'sortwizard.css|screen';
		$strJs = self::PATH.'sortwizard.js';

		if (!\in_array($strCss, $GLOBALS['TL_CSS'] ?? array(), true))
		{
			$GLOBALS['TL_CSS'][] = $strCss;
		}

		if (!\in_array($strJs, $GLOBALS['TL_JAVASCRIPT'] ?? array(), true))
		{
			$GLOBALS['TL_JAVASCRIPT'][] = $strJs;
		}
	}
}
