<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

/*
 * Ergaenzungen an tl_user: Rechte auf die Fotoalben-Archive
 *
 * Eingehaengt wird hinter der Gruppe „Dateisystemrechte“ (fop), so wie es auch
 * die Kernmodule tun. Der PaletteManipulator ersetzt das frueher benutzte
 * str_replace, das an einer geaenderten Kernpalette scheitern wuerde.
 */
foreach (array('extend', 'custom') as $strPalette)
{
	if (!isset($GLOBALS['TL_DCA']['tl_user']['palettes'][$strPalette]))
	{
		continue;
	}

	if (false !== strpos($GLOBALS['TL_DCA']['tl_user']['palettes'][$strPalette], 'photoalbums2s'))
	{
		continue;
	}

	PaletteManipulator::create()
		->addLegend('photoalbums2_legend', 'filemounts_legend', PaletteManipulator::POSITION_AFTER, true)
		->addField(array('photoalbums2s', 'photoalbums2p'), 'photoalbums2_legend', PaletteManipulator::POSITION_APPEND)
		->applyToPalette($strPalette, 'tl_user');
}

$GLOBALS['TL_DCA']['tl_user']['fields']['photoalbums2s'] = array
(
	'label'      => &$GLOBALS['TL_LANG']['tl_user']['photoalbums2s'],
	'exclude'    => true,
	'inputType'  => 'checkbox',
	'foreignKey' => 'tl_photoalbums2_archive.title',
	'eval'       => array('multiple' => true),
	'sql'        => "blob NULL",
);

$GLOBALS['TL_DCA']['tl_user']['fields']['photoalbums2p'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_user']['photoalbums2p'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'options'   => array('create', 'delete'),
	'reference' => &$GLOBALS['TL_LANG']['MSC'],
	'eval'      => array('multiple' => true),
	'sql'       => "blob NULL",
);
