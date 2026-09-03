<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

use Contao\DataContainer;
use Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer\ContentListener;
use Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer\TemplateListener;
use Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer\TimeFilterListener;

/*
 * Ergaenzungen an tl_content fuer das Inhaltselement „Fotoalbum“
 */
$GLOBALS['TL_DCA']['tl_content']['config']['onsubmit_callback'][] = array(TimeFilterListener::class, 'onSubmit');

$GLOBALS['TL_DCA']['tl_content']['palettes']['__selector__'][] = 'pa2TimeFilter';

/*
 * `guests` gibt es unter Contao 5 nicht mehr; ein Palettenfeld ohne
 * Felddefinition laesst den Data Container abbrechen.
 */
$strExpertLegend = '{expert_legend:hide},'.(isset($GLOBALS['TL_DCA']['tl_content']['fields']['guests']) ? 'guests,' : '').'cssID';

$GLOBALS['TL_DCA']['tl_content']['palettes']['photoalbums2'] = '{type_legend},type,headline;{config_legend},pa2Album;{pa2Template_legend},pa2ImageViewTemplate,pa2ImagesTemplate,pa2ImagesShowHeadline,pa2ImagesShowTitle,pa2ImagesShowTeaser;{pa2Image_legend},pa2ImagesImageSize,pa2ImagesImageMargin,pa2ImagesPerRow,pa2ImagesPerPage,pa2NumberOfImages;{pa2Meta_legend:hide},pa2ImagesShowMetaDescriptions,pa2ImagesMetaFields;{pa2TimeFilter_legend:hide},pa2TimeFilter;{pa2Other_legend:hide},pa2Teaser;{protected_legend:hide},protected;'.$strExpertLegend;

$GLOBALS['TL_DCA']['tl_content']['subpalettes']['pa2TimeFilter'] = 'pa2TimeFilterStart,pa2TimeFilterEnd';

/*
 * Felder
 */
$GLOBALS['TL_DCA']['tl_content']['fields']['pa2Album'] = array
(
	'label'            => &$GLOBALS['TL_LANG']['tl_content']['pa2Album'],
	'exclude'          => true,
	'inputType'        => 'select',
	'options_callback' => array(ContentListener::class, 'getAlbums'),
	'eval'             => array('mandatory' => true, 'chosen' => true),
	'wizard'           => array
	(
		array(ContentListener::class, 'editAlbum'),
	),
	'sql' => "int(10) unsigned NOT NULL default '0'",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['pa2ImageViewTemplate'] = array
(
	'label'            => &$GLOBALS['TL_LANG']['tl_content']['pa2ImageViewTemplate'],
	'exclude'          => true,
	'flag'             => DataContainer::SORT_ASC,
	'inputType'        => 'select',
	'options_callback' => array(TemplateListener::class, 'getWrapTemplates'),
	'eval'             => array('chosen' => true, 'tl_class' => 'w50'),
	'sql'              => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['pa2ImagesTemplate'] = array
(
	'label'            => &$GLOBALS['TL_LANG']['tl_content']['pa2ImagesTemplate'],
	'exclude'          => true,
	'flag'             => DataContainer::SORT_ASC,
	'inputType'        => 'select',
	'options_callback' => array(TemplateListener::class, 'getImageTemplates'),
	'eval'             => array('chosen' => true, 'tl_class' => 'w50'),
	'sql'              => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['pa2ImagesShowHeadline'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_content']['pa2ImagesShowHeadline'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'default'   => 1,
	'eval'      => array('tl_class' => 'clr'),
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['pa2ImagesShowTitle'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_content']['pa2ImagesShowTitle'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'default'   => 1,
	'eval'      => array('tl_class' => 'clr'),
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['pa2ImagesShowTeaser'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_content']['pa2ImagesShowTeaser'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'default'   => 1,
	'eval'      => array('tl_class' => 'clr'),
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['pa2ImagesImageSize'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_content']['pa2ImagesImageSize'],
	'exclude'   => true,
	'inputType' => 'imageSize',
	'reference' => &$GLOBALS['TL_LANG']['MSC'],
	'eval'      => array('rgxp' => 'natural', 'includeBlankOption' => true, 'nospace' => true, 'helpwizard' => true, 'tl_class' => 'w50'),
	'sql'       => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['pa2ImagesImageMargin'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_content']['pa2ImagesImageMargin'],
	'exclude'   => true,
	'inputType' => 'trbl',
	'options'   => array('px', '%', 'em', 'rem', 'ex', 'pt', 'pc', 'in', 'cm', 'mm'),
	'eval'      => array('includeBlankOption' => true, 'tl_class' => 'w50'),
	'sql'       => "varchar(128) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['pa2ImagesPerRow'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_content']['pa2ImagesPerRow'],
	'exclude'   => true,
	'inputType' => 'select',
	'default'   => 2,
	'options'   => array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12),
	'eval'      => array('mandatory' => true, 'chosen' => true, 'tl_class' => 'clr'),
	'sql'       => "smallint(5) unsigned NOT NULL default '2'",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['pa2ImagesPerPage'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_content']['pa2ImagesPerPage'],
	'default'   => 24,
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'natural', 'tl_class' => 'w50'),
	'sql'       => "smallint(5) unsigned NOT NULL default '24'",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['pa2NumberOfImages'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_content']['pa2NumberOfImages'],
	'default'   => 0,
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'natural', 'tl_class' => 'w50'),
	'sql'       => "smallint(5) unsigned NOT NULL default '0'",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['pa2ImagesShowMetaDescriptions'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_content']['pa2ImagesShowMetaDescriptions'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'default'   => 1,
	'eval'      => array('tl_class' => 'clr'),
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['pa2ImagesMetaFields'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_content']['pa2ImagesMetaFields'],
	'exclude'   => true,
	'inputType' => 'checkboxWizard',
	'options'   => $GLOBALS['pa2']['metaFields'],
	'reference' => &$GLOBALS['TL_LANG']['PA2']['pa2MetaFieldOptions'],
	'eval'      => array('multiple' => true, 'tl_class' => 'cbxes'),
	'sql'       => "blob NULL",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['pa2TimeFilter'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_content']['pa2TimeFilter'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => true),
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['pa2TimeFilterStart'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_content']['pa2TimeFilterStart'],
	'exclude'   => true,
	'inputType' => 'timePeriod',
	'default'   => 'days',
	'options'   => $GLOBALS['pa2']['timeFilterOptions'],
	'reference' => &$GLOBALS['TL_LANG']['PA2']['pa2TimeFilterOptions'],
	'eval'      => array('mandatory' => true, 'rgxp' => 'natural', 'tl_class' => 'w50'),
	'sql'       => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['pa2TimeFilterEnd'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_content']['pa2TimeFilterEnd'],
	'exclude'   => true,
	'inputType' => 'timePeriod',
	'default'   => 'days',
	'options'   => $GLOBALS['pa2']['timeFilterOptions'],
	'reference' => &$GLOBALS['TL_LANG']['PA2']['pa2TimeFilterOptions'],
	'eval'      => array('mandatory' => true, 'rgxp' => 'natural', 'tl_class' => 'w50'),
	'sql'       => "varchar(64) NOT NULL default ''",
);

/*
 * Der Teaser stand unter photoalbums2 in tl_translation_fields; das Feld
 * fuehrte nur die Verweisnummer und war deshalb eine Ganzzahlspalte. Jetzt
 * steht der Text selbst darin — den Umzug erledigt die Migration
 * TranslationFieldsMigration.
 */
$GLOBALS['TL_DCA']['tl_content']['fields']['pa2Teaser'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_content']['pa2Teaser'],
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => array('rte' => 'tinyMCE', 'tl_class' => 'clr'),
	'sql'       => "text NULL",
);
