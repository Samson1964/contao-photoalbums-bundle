<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Helper;

use Contao\StringUtil;
use Contao\System;

/**
 * Erzeugt Daumennaegel fuer die Anzeige im Backend.
 *
 * Der frueher benutzte `Controller::getImage()` ist unter Contao 5 entfallen.
 * Der Dienst `contao.image.factory` gibt es dagegen in beiden Fassungen und
 * ist in beiden oeffentlich.
 */
class Thumbnail
{
	/**
	 * Erzeugt das img-Element eines Daumennagels.
	 *
	 * @param string $strPath Projektrelativer Pfad der Bilddatei
	 * @param string $strAlt  Alternativtext, ueblicherweise der Dateiname
	 * @param int    $intWidth  Hoechstbreite in Bildpunkten
	 * @param int    $intHeight Hoechsthoehe in Bildpunkten
	 *
	 * @return string Das img-Element oder eine leere Zeichenkette, wenn sich
	 *                aus der Datei kein Bild erzeugen laesst (fehlende Datei,
	 *                unbekanntes Format, zu grosse Abmessungen)
	 */
	public static function generate(string $strPath, string $strAlt = '', int $intWidth = 80, int $intHeight = 60): string
	{
		if ('' === $strPath)
		{
			return '';
		}

		$strProjectDir = Runtime::getProjectDir();

		if (!is_file($strProjectDir.'/'.$strPath))
		{
			return '';
		}

		try
		{
			$objImage = System::getContainer()
				->get('contao.image.factory')
				->create($strProjectDir.'/'.$strPath, array($intWidth, $intHeight, 'center_center'));

			return '<img src="'.StringUtil::specialchars($objImage->getUrl($strProjectDir)).'" alt="'.StringUtil::specialchars($strAlt).'" width="'.$intWidth.'" height="'.$intHeight.'">';
		}
		catch (\Throwable $e)
		{
			return '';
		}
	}
}
