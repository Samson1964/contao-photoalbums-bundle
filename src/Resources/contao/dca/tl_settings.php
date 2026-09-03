<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

/*
 * Ergaenzung an tl_settings
 *
 * tl_settings ist ein DC_File-Datenbereich und schreibt in die
 * localconfig.php; ein 'sql'-Schluessel waere hier wirkungslos und fehlt
 * deshalb bewusst.
 */
if (!isset($GLOBALS['TL_DCA']['tl_settings']['palettes']['default']))
{
	$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] = '';
}

$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] .= ';{photoalbums2_legend},pa2HidePreviewImageInBackend';

$GLOBALS['TL_DCA']['tl_settings']['fields']['pa2HidePreviewImageInBackend'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_settings']['pa2HidePreviewImageInBackend'],
	'inputType' => 'checkbox',
	'eval'      => array('tl_class' => 'w50'),
);
