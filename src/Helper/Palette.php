<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Helper;

/**
 * Kleine Hilfen fuer den Umgang mit Paletten.
 */
class Palette
{
	/**
	 * Nimmt ein Feld aus einer Palette heraus.
	 *
	 * Gebraucht wird das fuer Felder, die nur bei einer bestimmten Einstellung
	 * sichtbar sein sollen, sich aber nicht ueber `subpalettes` abbilden
	 * lassen — etwa der Sortier-Assistent, der nur bei „Eigene Sortierung“
	 * erscheint.
	 *
	 * @param string $strTable   Name der Tabelle
	 * @param string $strPalette Name der Palette
	 * @param string $strField   Name des zu entfernenden Feldes
	 *
	 * @return void Fehlt die Palette, geschieht nichts
	 */
	public static function removeField(string $strTable, string $strPalette, string $strField): void
	{
		if (!isset($GLOBALS['TL_DCA'][$strTable]['palettes'][$strPalette]))
		{
			return;
		}

		$GLOBALS['TL_DCA'][$strTable]['palettes'][$strPalette] = preg_replace(
			'#,('.preg_quote($strField, '#').')([,;])#',
			'$2',
			$GLOBALS['TL_DCA'][$strTable]['palettes'][$strPalette]
		);
	}
}
