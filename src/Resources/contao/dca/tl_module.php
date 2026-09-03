<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

use Contao\DataContainer;
use Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer\ModuleListener;
use Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer\TemplateListener;
use Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer\TimeFilterListener;

/*
 * Ergaenzungen an tl_module fuer die drei Fotoalben-Module
 */
$GLOBALS['TL_DCA']['tl_module']['config']['onload_callback'][] = array(ModuleListener::class, 'fixPalette');
$GLOBALS['TL_DCA']['tl_module']['config']['onsubmit_callback'][] = array(TimeFilterListener::class, 'onSubmit');
$GLOBALS['TL_DCA']['tl_module']['config']['onsubmit_callback'][] = array(ModuleListener::class, 'handleListAndViewModule');

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'pa2TimeFilter';

/*
 * Die Palette fuer die Experten-Einstellungen unterscheidet sich zwischen den
 * Contao-Fassungen: `guests` gibt es unter Contao 5 nicht mehr, und ein
 * Palettenfeld ohne Felddefinition laesst den Data Container abbrechen.
 */
$strExpertLegend = '{expert_legend:hide},'.(isset($GLOBALS['TL_DCA']['tl_module']['fields']['guests']) ? 'guests,' : '').'cssID';

$GLOBALS['TL_DCA']['tl_module']['palettes']['photoalbums2'] = '{title_legend},name,headline,type;{config_legend},pa2Mode';

$GLOBALS['TL_DCA']['tl_module']['palettes']['pa2_on_one_page'] = '{title_legend},name,headline,type;{config_legend},pa2Mode,pa2PreviewImage;{pa2Album_legend},pa2Archives,pa2AlbumSortType,pa2AlbumSort;{pa2Template_legend},pa2AlbumViewTemplate,pa2ImageViewTemplate,pa2AlbumsTemplate,pa2ImagesTemplate,pa2AlbumsShowHeadline,pa2ImagesShowHeadline,pa2AlbumsShowTitle,pa2ImagesShowTitle,pa2AlbumsShowTeaser,pa2ImagesShowTeaser;{pa2Image_legend},pa2AlbumsImageSize,pa2ImagesImageSize,pa2AlbumsImageMargin,pa2ImagesImageMargin,pa2AlbumsPerRow,pa2ImagesPerRow,pa2AlbumsPerPage,pa2ImagesPerPage,pa2NumberOfAlbums,pa2NumberOfImages;{pa2Meta_legend:hide},pa2AlbumsShowMetaDescriptions,pa2ImagesShowMetaDescriptions,pa2AlbumsMetaFields,pa2ImagesMetaFields;{pa2TimeFilter_legend:hide},pa2TimeFilter;{pa2Other_legend:hide},pa2Teaser;{protected_legend:hide},protected;'.$strExpertLegend;

$GLOBALS['TL_DCA']['tl_module']['palettes']['pa2_only_album_view'] = '{title_legend},name,headline,type;{config_legend},pa2Mode,pa2PreviewImage;{pa2Album_legend},pa2Archives,pa2AlbumSortType,pa2AlbumSort;{pa2Template_legend},pa2AlbumViewTemplate,pa2AlbumsTemplate,pa2AlbumsShowHeadline,pa2AlbumsShowTitle,pa2AlbumsShowTeaser;{pa2Image_legend},pa2AlbumsImageSize,pa2AlbumsImageMargin,pa2AlbumsPerRow,pa2AlbumsPerPage,pa2NumberOfAlbums;{pa2Meta_legend:hide},pa2AlbumsShowMetaDescriptions,pa2AlbumsMetaFields;{pa2TimeFilter_legend:hide},pa2TimeFilter;{pa2Other_legend:hide},pa2Teaser;{protected_legend:hide},protected;'.$strExpertLegend;

$GLOBALS['TL_DCA']['tl_module']['palettes']['pa2_with_detail_page'] = '{title_legend},name,headline,type;{config_legend},pa2Mode,pa2PreviewImage,pa2OverviewPage,pa2DetailPage;{pa2Album_legend},pa2Archives,pa2AlbumSortType,pa2AlbumSort;{pa2Template_legend},pa2AlbumViewTemplate,pa2ImageViewTemplate,pa2AlbumsTemplate,pa2ImagesTemplate,pa2AlbumsShowHeadline,pa2ImagesShowHeadline,pa2AlbumsShowTitle,pa2ImagesShowTitle,pa2AlbumsShowTeaser,pa2ImagesShowTeaser;{pa2Image_legend},pa2AlbumsImageSize,pa2ImagesImageSize,pa2AlbumsImageMargin,pa2ImagesImageMargin,pa2AlbumsPerRow,pa2ImagesPerRow,pa2AlbumsPerPage,pa2ImagesPerPage,pa2NumberOfAlbums,pa2NumberOfImages;{pa2Meta_legend:hide},pa2AlbumsShowMetaDescriptions,pa2ImagesShowMetaDescriptions,pa2AlbumsMetaFields,pa2ImagesMetaFields;{pa2TimeFilter_legend:hide},pa2TimeFilter;{pa2Other_legend:hide},pa2Teaser;{protected_legend:hide},protected;'.$strExpertLegend;

$GLOBALS['TL_DCA']['tl_module']['palettes']['photoalbums2list'] = '{title_legend},name,headline,type;{config_legend},pa2PreviewImage,pa2DetailPage;{pa2Album_legend},pa2Archives,pa2AlbumSortType,pa2AlbumSort;{pa2Template_legend},pa2AlbumViewTemplate,pa2AlbumsTemplate,pa2AlbumsShowHeadline,pa2AlbumsShowTitle,pa2AlbumsShowTeaser;{pa2Image_legend},pa2AlbumsImageSize,pa2AlbumsImageMargin,pa2AlbumsPerRow,pa2AlbumsPerPage,pa2NumberOfAlbums;{pa2Meta_legend:hide},pa2AlbumsShowMetaDescriptions,pa2AlbumsMetaFields;{pa2TimeFilter_legend:hide},pa2TimeFilter;{pa2Other_legend:hide},pa2Teaser;{protected_legend:hide},protected;'.$strExpertLegend;

$GLOBALS['TL_DCA']['tl_module']['palettes']['photoalbums2view'] = '{title_legend},name,headline,type;{config_legend},pa2OverviewPage;{pa2Album_legend},pa2Archives;{pa2Template_legend},pa2ImageViewTemplate,pa2ImagesTemplate,pa2ImagesShowHeadline,pa2ImagesShowTitle,pa2ImagesShowTeaser;{pa2Image_legend},pa2ImagesImageSize,pa2ImagesImageMargin,pa2ImagesPerRow,pa2ImagesPerPage,pa2NumberOfImages;{pa2Meta_legend:hide},pa2ImagesShowMetaDescriptions,pa2ImagesMetaFields;{pa2TimeFilter_legend:hide},pa2TimeFilter;{pa2Other_legend:hide},pa2Teaser;{protected_legend:hide},protected;'.$strExpertLegend;

$GLOBALS['TL_DCA']['tl_module']['subpalettes']['pa2TimeFilter'] = 'pa2TimeFilterStart,pa2TimeFilterEnd';

/*
 * Felder
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['pa2Mode'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2Mode'],
	'exclude'   => true,
	'inputType' => 'select',
	'options'   => array('pa2_on_one_page', 'pa2_only_album_view', 'pa2_with_detail_page'),
	'reference' => &$GLOBALS['TL_LANG']['PA2']['moduleModeTypes'],
	'default'   => 'pa2_on_one_page',
	'eval'      => array('submitOnChange' => true, 'helpwizard' => true, 'tl_class' => 'w50'),
	'sql'       => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2PreviewImage'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2PreviewImage'],
	'exclude'   => true,
	'inputType' => 'select',
	'default'   => 'use_album_options',
	'options'   => $GLOBALS['pa2']['modulePreviewImageTypes'],
	'reference' => &$GLOBALS['TL_LANG']['PA2']['previewImageModuleTypes'],
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2OverviewPage'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2OverviewPage'],
	'exclude'   => true,
	'inputType' => 'pageTree',
	'eval'      => array('mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'),
	'sql'       => "varchar(10) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2DetailPage'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2DetailPage'],
	'exclude'   => true,
	'inputType' => 'pageTree',
	'eval'      => array('mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'),
	'sql'       => "varchar(10) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2Archives'] = array
(
	'label'            => &$GLOBALS['TL_LANG']['tl_module']['pa2Archives'],
	'exclude'          => true,
	'inputType'        => 'checkbox',
	'options_callback' => array(ModuleListener::class, 'getArchives'),
	'eval'             => array('mandatory' => true, 'multiple' => true),
	'sql'              => "blob NULL",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2AlbumSortType'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2AlbumSortType'],
	'exclude'   => true,
	'inputType' => 'select',
	'options'   => $GLOBALS['pa2']['albumSortTypes'],
	'reference' => &$GLOBALS['TL_LANG']['PA2']['albumSortTypes'],
	'eval'      => array('submitOnChange' => true),
	'sql'       => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2AlbumSort'] = array
(
	'label'            => &$GLOBALS['TL_LANG']['tl_module']['pa2AlbumSort'],
	'exclude'          => true,
	'inputType'        => 'pa2SortWizard',
	'options_callback' => array(ModuleListener::class, 'getAlbumSort'),
	'eval'             => array('tl_class' => 'clr'),
	'sql'              => "blob NULL",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2AlbumViewTemplate'] = array
(
	'label'            => &$GLOBALS['TL_LANG']['tl_module']['pa2AlbumViewTemplate'],
	'exclude'          => true,
	'flag'             => DataContainer::SORT_ASC,
	'inputType'        => 'select',
	'options_callback' => array(TemplateListener::class, 'getWrapTemplates'),
	'eval'             => array('chosen' => true, 'tl_class' => 'w50'),
	'sql'              => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2ImageViewTemplate'] = array
(
	'label'            => &$GLOBALS['TL_LANG']['tl_module']['pa2ImageViewTemplate'],
	'exclude'          => true,
	'flag'             => DataContainer::SORT_ASC,
	'inputType'        => 'select',
	'options_callback' => array(TemplateListener::class, 'getWrapTemplates'),
	'eval'             => array('chosen' => true, 'tl_class' => 'w50'),
	'sql'              => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2AlbumsTemplate'] = array
(
	'label'            => &$GLOBALS['TL_LANG']['tl_module']['pa2AlbumsTemplate'],
	'exclude'          => true,
	'flag'             => DataContainer::SORT_ASC,
	'inputType'        => 'select',
	'options_callback' => array(TemplateListener::class, 'getAlbumTemplates'),
	'eval'             => array('chosen' => true, 'tl_class' => 'w50'),
	'sql'              => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2ImagesTemplate'] = array
(
	'label'            => &$GLOBALS['TL_LANG']['tl_module']['pa2ImagesTemplate'],
	'exclude'          => true,
	'flag'             => DataContainer::SORT_ASC,
	'inputType'        => 'select',
	'options_callback' => array(TemplateListener::class, 'getImageTemplates'),
	'eval'             => array('chosen' => true, 'tl_class' => 'w50'),
	'sql'              => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2AlbumsShowHeadline'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2AlbumsShowHeadline'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'default'   => 1,
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2ImagesShowHeadline'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2ImagesShowHeadline'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'default'   => 1,
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2AlbumsShowTitle'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2AlbumsShowTitle'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'default'   => 1,
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2ImagesShowTitle'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2ImagesShowTitle'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'default'   => 1,
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2AlbumsShowTeaser'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2AlbumsShowTeaser'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'default'   => 1,
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2ImagesShowTeaser'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2ImagesShowTeaser'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'default'   => 1,
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2AlbumsImageSize'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2AlbumsImageSize'],
	'exclude'   => true,
	'inputType' => 'imageSize',
	'reference' => &$GLOBALS['TL_LANG']['MSC'],
	'eval'      => array('rgxp' => 'natural', 'includeBlankOption' => true, 'nospace' => true, 'helpwizard' => true, 'tl_class' => 'w50'),
	'sql'       => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2ImagesImageSize'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2ImagesImageSize'],
	'exclude'   => true,
	'inputType' => 'imageSize',
	'reference' => &$GLOBALS['TL_LANG']['MSC'],
	'eval'      => array('rgxp' => 'natural', 'includeBlankOption' => true, 'nospace' => true, 'helpwizard' => true, 'tl_class' => 'w50'),
	'sql'       => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2AlbumsImageMargin'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2AlbumsImageMargin'],
	'exclude'   => true,
	'inputType' => 'trbl',
	'options'   => array('px', '%', 'em', 'rem', 'ex', 'pt', 'pc', 'in', 'cm', 'mm'),
	'eval'      => array('includeBlankOption' => true, 'tl_class' => 'w50'),
	'sql'       => "varchar(128) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2ImagesImageMargin'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2ImagesImageMargin'],
	'exclude'   => true,
	'inputType' => 'trbl',
	'options'   => array('px', '%', 'em', 'rem', 'ex', 'pt', 'pc', 'in', 'cm', 'mm'),
	'eval'      => array('includeBlankOption' => true, 'tl_class' => 'w50'),
	'sql'       => "varchar(128) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2AlbumsPerRow'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2AlbumsPerRow'],
	'exclude'   => true,
	'inputType' => 'select',
	'default'   => 1,
	'options'   => array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12),
	'eval'      => array('mandatory' => true, 'chosen' => true, 'tl_class' => 'w50'),
	'sql'       => "smallint(5) unsigned NOT NULL default '1'",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2ImagesPerRow'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2ImagesPerRow'],
	'exclude'   => true,
	'inputType' => 'select',
	'default'   => 2,
	'options'   => array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12),
	'eval'      => array('mandatory' => true, 'chosen' => true, 'tl_class' => 'w50'),
	'sql'       => "smallint(5) unsigned NOT NULL default '2'",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2AlbumsPerPage'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2AlbumsPerPage'],
	'default'   => 5,
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'natural', 'tl_class' => 'w50'),
	'sql'       => "smallint(5) unsigned NOT NULL default '5'",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2ImagesPerPage'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2ImagesPerPage'],
	'default'   => 24,
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'natural', 'tl_class' => 'w50'),
	'sql'       => "smallint(5) unsigned NOT NULL default '24'",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2NumberOfAlbums'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2NumberOfAlbums'],
	'default'   => 0,
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'natural', 'tl_class' => 'w50'),
	'sql'       => "smallint(5) unsigned NOT NULL default '0'",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2NumberOfImages'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2NumberOfImages'],
	'default'   => 0,
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'natural', 'tl_class' => 'w50'),
	'sql'       => "smallint(5) unsigned NOT NULL default '0'",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2AlbumsShowMetaDescriptions'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2AlbumsShowMetaDescriptions'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'default'   => 1,
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2ImagesShowMetaDescriptions'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2ImagesShowMetaDescriptions'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'default'   => 1,
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2AlbumsMetaFields'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2AlbumsMetaFields'],
	'exclude'   => true,
	'inputType' => 'checkboxWizard',
	'options'   => $GLOBALS['pa2']['metaFields'],
	'reference' => &$GLOBALS['TL_LANG']['PA2']['pa2MetaFieldOptions'],
	'eval'      => array('multiple' => true, 'tl_class' => 'w50 cbxes'),
	'sql'       => "blob NULL",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2ImagesMetaFields'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2ImagesMetaFields'],
	'exclude'   => true,
	'inputType' => 'checkboxWizard',
	'options'   => $GLOBALS['pa2']['metaFields'],
	'reference' => &$GLOBALS['TL_LANG']['PA2']['pa2MetaFieldOptions'],
	'eval'      => array('multiple' => true, 'tl_class' => 'w50 cbxes'),
	'sql'       => "blob NULL",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2TimeFilter'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2TimeFilter'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => true),
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2TimeFilterStart'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2TimeFilterStart'],
	'exclude'   => true,
	'inputType' => 'timePeriod',
	'default'   => 'days',
	'options'   => $GLOBALS['pa2']['timeFilterOptions'],
	'reference' => &$GLOBALS['TL_LANG']['PA2']['pa2TimeFilterOptions'],
	'eval'      => array('mandatory' => true, 'rgxp' => 'natural', 'tl_class' => 'w50'),
	'sql'       => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['pa2TimeFilterEnd'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2TimeFilterEnd'],
	'exclude'   => true,
	'inputType' => 'timePeriod',
	'default'   => 'days',
	'options'   => $GLOBALS['pa2']['timeFilterOptions'],
	'reference' => &$GLOBALS['TL_LANG']['PA2']['pa2TimeFilterOptions'],
	'eval'      => array('mandatory' => true, 'rgxp' => 'natural', 'tl_class' => 'w50'),
	'sql'       => "varchar(64) NOT NULL default ''",
);

/*
 * Der Teaser stand unter photoalbums2 in tl_translation_fields und wurde hier
 * nur als Verweisnummer gefuehrt. Jetzt steht der Text selbst im Feld.
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['pa2Teaser'] = array
(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['pa2Teaser'],
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => array('rte' => 'tinyMCE', 'tl_class' => 'clr'),
	'sql'       => "text NULL",
);
