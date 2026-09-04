<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

use Contao\BackendUser;
use Contao\DataContainer;
use Contao\DC_Table;
use Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer\AlbumListener;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Runtime;

/*
 * Tabelle tl_photoalbums2_album
 *
 * Der Tabellenname stammt aus der Vorgaengererweiterung photoalbums2 und
 * bleibt bewusst unveraendert, damit Bestandsinstallationen ohne Datenumzug
 * auf dieses Bundle wechseln koennen.
 *
 * Die Felder `event`, `place`, `photographer` und `description` fuehrten unter
 * photoalbums2 nur eine Verweisnummer auf `tl_translation_fields`. Dieses
 * Bundle speichert dort wieder den Text selbst; den Umzug erledigt die
 * Migration TranslationFieldsMigration.
 */
$GLOBALS['TL_DCA']['tl_photoalbums2_album'] = array
(
	// Konfiguration
	'config' => array
	(
		'dataContainer'     => DC_Table::class,
		'enableVersioning'  => true,
		'ptable'            => 'tl_photoalbums2_archive',
		'onload_callback'   => array
		(
			array(AlbumListener::class, 'checkPermission'),
			array(AlbumListener::class, 'generateFeed'),
			array(AlbumListener::class, 'generatePalette'),
		),
		'oncut_callback'    => array
		(
			array(AlbumListener::class, 'scheduleUpdate'),
		),
		'ondelete_callback' => array
		(
			array(AlbumListener::class, 'scheduleUpdate'),
		),
		'onsubmit_callback' => array
		(
			array(AlbumListener::class, 'adjustTime'),
			array(AlbumListener::class, 'scheduleUpdate'),
		),
		'sql' => array
		(
			'keys' => array
			(
				'id'    => 'primary',
				'pid'   => 'index',
				'alias' => 'index',
			),
		),
	),

	// Liste
	'list' => array
	(
		'sorting' => array
		(
			'mode'                  => DataContainer::MODE_PARENT,
			'fields'                => array('sorting'),
			'headerFields'          => array('title', 'tstamp', 'protected', 'allowComments', 'makeFeed'),
			'panelLayout'           => 'search,limit',
			'child_record_callback' => array(AlbumListener::class, 'listAlbums'),
		),
		'global_operations' => array
		(
			'all' => array
			(
				'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
				'href'       => 'act=select',
				'class'      => 'header_edit_all',
				'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
			),
		),
		'operations' => array
		(
			'edit' => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['edit'],
				'href'  => 'act=edit',
				'icon'  => 'edit.svg',
			),
			'copy' => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['copy'],
				'href'  => 'act=paste&amp;mode=copy',
				'icon'  => 'copy.svg',
			),
			'cut' => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['cut'],
				'href'  => 'act=paste&amp;mode=cut',
				'icon'  => 'cut.svg',
			),
			'delete' => array
			(
				'label'      => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['delete'],
				'href'       => 'act=delete',
				'icon'       => 'delete.svg',
				'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? '').'\'))return false;Backend.getScrollOffset();"',
			),
			// Der eingebaute Umschalter: Feld "published" traegt 'toggle' => true,
			// deshalb braucht es hier kein eigenes button_callback mehr
			'toggle' => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['toggle'],
				'href'  => 'act=toggle&amp;field=published',
				'icon'  => 'visible.svg',
			),
			'show' => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['show'],
				'href'  => 'act=show',
				'icon'  => 'show.svg',
			),
		),
	),

	// Paletten
	'palettes' => array
	(
		'__selector__' => array('imageSortType', 'previewImageType', 'protected'),
		'default'      => '{title_legend},title,alias,author;{date_legend},startdate,enddate;{images_legend},images,imageSortType,imageSort,previewImageType,previewImage;{info_legend},event,place,photographer,description;{protected_legend},protected;{expert_legend:hide},cssClass,noComments;{published_legend},published,start,stop',
	),

	// Unterpaletten
	'subpalettes' => array
	(
		'protected' => 'users,groups',
	),

	// Felder
	'fields' => array
	(
		'id' => array
		(
			'label'  => array('ID'),
			'search' => true,
			'sql'    => "int(10) unsigned NOT NULL auto_increment",
		),
		'pid' => array
		(
			'foreignKey' => 'tl_photoalbums2_archive.title',
			'sql'        => "int(10) unsigned NOT NULL default '0'",
			'relation'   => array('type' => 'belongsTo', 'load' => 'eager'),
		),
		'sorting' => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'tstamp' => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'title' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['title'],
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'alias' => array
		(
			'label'         => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['alias'],
			'exclude'       => true,
			'search'        => true,
			'inputType'     => 'text',
			'eval'          => array('maxlength' => 128, 'unique' => true, 'tl_class' => 'w50'),
			'save_callback' => array
			(
				array(AlbumListener::class, 'generateAlias'),
			),
			'sql' => "varbinary(128) NOT NULL default ''",
		),
		'author' => array
		(
			'label'      => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['author'],
			// Nur im Backend nach dem angemeldeten Benutzer fragen: Im Frontend
			// gaebe es keinen, und Contao 4.13 loest hier keine Closures auf
			'default'    => Runtime::isBackend() ? BackendUser::getInstance()->id : 0,
			'exclude'    => true,
			'filter'     => true,
			'inputType'  => 'select',
			'foreignKey' => 'tl_user.name',
			'eval'       => array('doNotCopy' => true, 'chosen' => true, 'mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'),
			'sql'        => "int(10) unsigned NOT NULL default '0'",
			'relation'   => array('type' => 'hasOne', 'load' => 'eager'),
		),

		// Start- und Enddatum sind Unix-Zeitstempel und duerfen negativ sein.
		// varchar(11) statt der frueheren varchar(10): Ein Zeitstempel vor
		// 1970 traegt ein Minuszeichen und braucht die elfte Stelle.
		'startdate' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['startdate'],
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'text',
			'eval'      => array('rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'),
			'sql'       => "varchar(11) NOT NULL default ''",
		),
		'enddate' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['enddate'],
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'text',
			'eval'      => array('rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'),
			'sql'       => "varchar(11) NOT NULL default ''",
		),

		'images' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['images'],
			'exclude'   => true,
			'inputType' => 'fileTree',
			'eval'      => array('mandatory' => true, 'multiple' => true, 'fieldType' => 'checkbox', 'files' => true, 'extensions' => $GLOBALS['pa2']['mediaExtensions']),
			'sql'       => "blob NULL",
		),
		'imageSortType' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['imageSortType'],
			'exclude'   => true,
			'inputType' => 'select',
			'options'   => $GLOBALS['pa2']['imageSortTypes'],
			'reference' => &$GLOBALS['TL_LANG']['PA2']['imageSortTypes'],
			'eval'      => array('submitOnChange' => true, 'tl_class' => 'w50'),
			'sql'       => "varchar(64) NOT NULL default ''",
		),
		'imageSort' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['imageSort'],
			'exclude'   => true,
			'inputType' => 'pa2ImageSortWizard',
			'eval'      => array('sortfiles' => 'images', 'extensions' => $GLOBALS['pa2']['mediaExtensions'], 'tl_class' => 'clr'),
			'sql'       => "blob NULL",
		),
		'previewImageType' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['previewImageType'],
			'exclude'   => true,
			'inputType' => 'select',
			'options'   => $GLOBALS['pa2']['albumPreviewImageTypes'],
			'reference' => &$GLOBALS['TL_LANG']['PA2']['albumPreviewImageTypes'],
			'eval'      => array('submitOnChange' => true, 'tl_class' => 'w50 clr'),
			'sql'       => "varchar(64) NOT NULL default ''",
		),
		'previewImage' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['previewImage'],
			'exclude'   => true,
			'inputType' => 'fileTree',
			'eval'      => array('mandatory' => true, 'fieldType' => 'radio', 'files' => true, 'filesOnly' => true, 'extensions' => $GLOBALS['pa2']['imageExtensions'], 'tl_class' => 'clr'),
			'sql'       => "binary(16) NULL",
		),
		'event' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['event'],
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'text',
			'eval'      => array('maxlength' => 255, 'tl_class' => 'w50'),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'place' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['place'],
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'text',
			'eval'      => array('maxlength' => 255, 'tl_class' => 'w50'),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'photographer' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['photographer'],
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'text',
			'eval'      => array('maxlength' => 255, 'tl_class' => 'clr'),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'description' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['description'],
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'textarea',
			'eval'      => array('rte' => 'tinyMCE', 'tl_class' => 'clr'),
			'sql'       => "mediumtext NULL",
		),
		'protected' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['protected'],
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => array('submitOnChange' => true),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'users' => array
		(
			'label'      => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['users'],
			'exclude'    => true,
			'inputType'  => 'checkbox',
			'foreignKey' => 'tl_member.username',
			'eval'       => array('multiple' => true, 'tl_class' => 'w50 cbxes'),
			'sql'        => "blob NULL",
		),
		'groups' => array
		(
			'label'      => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['groups'],
			'exclude'    => true,
			'inputType'  => 'checkbox',
			'foreignKey' => 'tl_member_group.name',
			'eval'       => array('multiple' => true, 'tl_class' => 'w50 cbxes'),
			'sql'        => "blob NULL",
		),
		'cssClass' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['cssClass'],
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => array('tl_class' => 'w50'),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'noComments' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['noComments'],
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'checkbox',
			'eval'      => array('tl_class' => 'w50'),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'published' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['published'],
			'exclude'   => true,
			'filter'    => true,
			'flag'      => DataContainer::SORT_INITIAL_LETTER_DESC,
			'inputType' => 'checkbox',
			'eval'      => array('doNotCopy' => true),
			'toggle'    => true,
			'sql'       => "char(1) NOT NULL default ''",
		),
		'start' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['start'],
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => array('rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'),
			'sql'       => "varchar(11) NOT NULL default ''",
		),
		'stop' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_album']['stop'],
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => array('rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'),
			'sql'       => "varchar(11) NOT NULL default ''",
		),
	),
);
