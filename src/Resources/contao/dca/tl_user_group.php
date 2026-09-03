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
 * Ergaenzungen an tl_user_group: Rechte auf die Fotoalben-Archive
 */
if (isset($GLOBALS['TL_DCA']['tl_user_group']['palettes']['default']) && false === strpos($GLOBALS['TL_DCA']['tl_user_group']['palettes']['default'], 'photoalbums2s'))
{
	PaletteManipulator::create()
		->addLegend('photoalbums2_legend', 'filemounts_legend', PaletteManipulator::POSITION_AFTER, true)
		->addField(array('photoalbums2s', 'photoalbums2p'), 'photoalbums2_legend', PaletteManipulator::POSITION_APPEND)
		->applyToPalette('default', 'tl_user_group');
}

$GLOBALS['TL_DCA']['tl_user_group']['fields']['photoalbums2s'] = array
(
	'label'      => &$GLOBALS['TL_LANG']['tl_user_group']['photoalbums2s'],
	'exclude'    => true,
	'inputType'  => 'checkbox',
	'foreignKey' => 'tl_photoalbums2_archive.title',
	'eval'       => array('multiple' => true),
	'sql'        => "blob NULL",
);

$GLOBALS['TL_DCA']['tl_user_group']['fields']['photoalbums2p'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_user_group']['photoalbums2p'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'options'   => array('create', 'delete'),
	'reference' => &$GLOBALS['TL_LANG']['MSC'],
	'eval'      => array('multiple' => true),
	'sql'       => "blob NULL",
);
