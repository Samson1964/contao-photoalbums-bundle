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
 * Ergaenzung an tl_layout: Schalter, um das mitgelieferte Stylesheet
 * abzuschalten.
 *
 * Die Urfassung hat das Feld per str_replace hinter `loadingOrder` gesetzt.
 * Das Feld gibt es unter Contao 5 nicht mehr; der PaletteManipulator haengt es
 * stattdessen an die Gruppe „Stil“ an, die in beiden Fassungen vorhanden ist.
 */
if (false === strpos($GLOBALS['TL_DCA']['tl_layout']['palettes']['default'] ?? '', 'skipPhotoalbums2'))
{
	PaletteManipulator::create()
		->addField('skipPhotoalbums2', 'style_legend', PaletteManipulator::POSITION_APPEND)
		->applyToPalette('default', 'tl_layout');
}

$GLOBALS['TL_DCA']['tl_layout']['fields']['skipPhotoalbums2'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_layout']['skipPhotoalbums2'],
	'default'   => '',
	'exclude'   => true,
	'inputType' => 'checkbox',
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "char(1) NOT NULL default ''",
);
