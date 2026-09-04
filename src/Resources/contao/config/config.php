<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

use Schachbulle\ContaoPhotoalbumsBundle\Elements\ContentPhotoalbums2;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Assets;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Runtime;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Video;
use Schachbulle\ContaoPhotoalbumsBundle\Model\AlbumModel;
use Schachbulle\ContaoPhotoalbumsBundle\Model\ArchiveModel;
use Schachbulle\ContaoPhotoalbumsBundle\Modules\ModulePhotoalbums2;
use Schachbulle\ContaoPhotoalbumsBundle\Modules\ModulePhotoalbums2List;
use Schachbulle\ContaoPhotoalbumsBundle\Modules\ModulePhotoalbums2View;
use Schachbulle\ContaoPhotoalbumsBundle\Widget\ImageSortWizard;
use Schachbulle\ContaoPhotoalbumsBundle\Widget\SortWizard;

/*
 * Stilvorlage fuer die Eingabemasken im Backend
 */
if (Runtime::isBackend())
{
	$GLOBALS['TL_CSS'][] = Assets::PATH.'photoalbums-be.css';
}

/*
 * Backend-Modul im Bereich "Inhalte"
 */
$GLOBALS['BE_MOD']['content']['photoalbums2'] = array
(
	'tables' => array('tl_photoalbums2_archive', 'tl_photoalbums2_album'),
	'icon'   => Assets::PATH.'images/icon.gif',
);

/*
 * Frontend-Module
 *
 * Die Registrierung ueber $GLOBALS['FE_MOD'] mit dem vollen Klassennamen
 * funktioniert unter Contao 4.13 wie unter Contao 5 (Module::findClass()).
 */
$GLOBALS['FE_MOD']['photoalbums2_legend'] = array
(
	'photoalbums2'     => ModulePhotoalbums2::class,
	'photoalbums2list' => ModulePhotoalbums2List::class,
	'photoalbums2view' => ModulePhotoalbums2View::class,
);

/*
 * Inhaltselement
 */
$GLOBALS['TL_CTE']['media']['photoalbums2'] = ContentPhotoalbums2::class;

/*
 * Modelle
 */
$GLOBALS['TL_MODELS']['tl_photoalbums2_archive'] = ArchiveModel::class;
$GLOBALS['TL_MODELS']['tl_photoalbums2_album'] = AlbumModel::class;

/*
 * Backend-Formularfelder
 */
$GLOBALS['BE_FFL']['pa2ImageSortWizard'] = ImageSortWizard::class;
$GLOBALS['BE_FFL']['pa2SortWizard'] = SortWizard::class;

/*
 * Rechte, die sich Benutzern und Benutzergruppen zuweisen lassen
 */
$GLOBALS['TL_PERMISSIONS'][] = 'photoalbums2s';
$GLOBALS['TL_PERMISSIONS'][] = 'photoalbums2p';

/*
 * Auswahllisten der Erweiterung
 *
 * Sie stehen bewusst in einem globalen Feld statt in Klassenkonstanten: Die
 * DCA-Dateien greifen unmittelbar darauf zu, und eine Installation kann sie
 * in der eigenen config.php ergaenzen.
 */
$GLOBALS['pa2'] = array();

/* Zugelassene Dateiendungen fuer Fotos */
$GLOBALS['pa2']['imageExtensions'] = 'png,jpg,jpeg,gif,webp,avif';

/*
 * Zugelassene Dateiendungen fuer Videos
 *
 * Alle vier duerfen mit Contaos Voreinstellung fuer `uploadTypes` ohne weitere
 * Einrichtung hochgeladen werden.
 */
$GLOBALS['pa2']['videoExtensions'] = Video::DEFAULT_EXTENSIONS;

/*
 * Was insgesamt in einem Album liegen darf
 *
 * Diese Liste steht an der Dateiauswahl des Albums und am Sortier-Assistenten.
 */
$GLOBALS['pa2']['mediaExtensions'] = $GLOBALS['pa2']['imageExtensions'].','.$GLOBALS['pa2']['videoExtensions'];

/* Sortierung der Fotos innerhalb eines Albums */
$GLOBALS['pa2']['imageSortTypes'] = array
(
	'metatitle_asc'  => 'metatitle_asc',
	'metatitle_desc' => 'metatitle_desc',
	'name_asc'       => 'name_asc',
	'name_desc'      => 'name_desc',
	'date_asc'       => 'date_asc',
	'date_desc'      => 'date_desc',
	'random'         => 'random',
	'custom'         => 'custom',
);

/* Sortierung der Alben in der Uebersicht */
$GLOBALS['pa2']['albumSortTypes'] = array
(
	'title_asc'           => 'title_asc',
	'title_desc'          => 'title_desc',
	'startdate_asc'       => 'startdate_asc',
	'startdate_desc'      => 'startdate_desc',
	'enddate_asc'         => 'enddate_asc',
	'enddate_desc'        => 'enddate_desc',
	'numberOfImages_asc'  => 'numberOfImages_asc',
	'numberOfImages_desc' => 'numberOfImages_desc',
	'random'              => 'random',
	'custom'              => 'custom',
);

/* Vorschaubild, im Album eingestellt */
$GLOBALS['pa2']['albumPreviewImageTypes'] = array
(
	'no_preview_image'     => 'no_preview_image',
	'random_preview_image' => 'random_preview_image',
	'first_preview_image'  => 'first_preview_image',
	'select_preview_image' => 'select_preview_image',
);

/* Vorschaubild, im Modul eingestellt */
$GLOBALS['pa2']['modulePreviewImageTypes'] = array
(
	'use_album_options'                  => 'use_album_options',
	'no_preview_images'                  => 'no_preview_images',
	'random_images'                      => 'random_images',
	'random_images_at_no_preview_images' => 'random_images_at_no_preview_images',
	'first_image_at_no_preview_images'   => 'first_image_at_no_preview_images',
);

/* Einheiten des Zeitfilters */
$GLOBALS['pa2']['timeFilterOptions'] = array('days', 'weeks', 'months', 'years');

/* Angaben, die unter einem Album ausgegeben werden koennen */
$GLOBALS['pa2']['metaFields'] = array('date', 'event', 'place', 'photographer', 'description', 'numberOfAllImages');
