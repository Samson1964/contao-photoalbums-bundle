<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

use Contao\DataContainer;
use Contao\DC_Table;
use Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer\ArchiveListener;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Runtime;

/*
 * Tabelle tl_photoalbums2_archive
 *
 * Der Tabellenname stammt aus der Vorgaengererweiterung photoalbums2 und
 * bleibt bewusst unveraendert, damit Bestandsinstallationen ohne Datenumzug
 * auf dieses Bundle wechseln koennen.
 */
$GLOBALS['TL_DCA']['tl_photoalbums2_archive'] = array
(
	// Konfiguration
	'config' => array
	(
		'dataContainer'     => DC_Table::class,
		'enableVersioning'  => true,
		'switchToEdit'      => true,
		'ctable'            => array('tl_photoalbums2_album'),
		'onload_callback'   => array
		(
			array(ArchiveListener::class, 'checkPermission'),
			array(ArchiveListener::class, 'generateFeed'),
		),
		'onsubmit_callback' => array
		(
			array(ArchiveListener::class, 'scheduleUpdate'),
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
			'mode'        => DataContainer::MODE_SORTED,
			'fields'      => array('title'),
			'panelLayout' => 'search,limit',
			'flag'        => DataContainer::SORT_INITIAL_LETTER_ASC,
		),
		'label' => array
		(
			'fields' => array('title'),
			'format' => '%s',
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
				'label'      => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['edit'],
				'href'       => 'table=tl_photoalbums2_album',
				'icon'       => 'edit.svg',
				'attributes' => 'class="contextmenu"',
			),
			'editheader' => array
			(
				'label'      => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['editheader'],
				'href'       => 'act=edit',
				'icon'       => 'header.svg',
				'attributes' => 'class="edit-header"',
			),
			'copy' => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['copy'],
				'href'  => 'act=copy',
				'icon'  => 'copy.svg',
			),
			'delete' => array
			(
				'label'           => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['delete'],
				'href'            => 'act=delete',
				'icon'            => 'delete.svg',
				'attributes'      => 'onclick="if (!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? '').'\')) return false; Backend.getScrollOffset();"',
				'button_callback' => array(ArchiveListener::class, 'deleteArchive'),
			),
			'show' => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['show'],
				'href'  => 'act=show',
				'icon'  => 'show.svg',
			),
		),
	),

	// Paletten
	'palettes' => array
	(
		'__selector__' => array('allowComments', 'protected', 'makeFeed'),
		'default'      => '{title_legend},title;{comments_legend:hide},allowComments;{protected_legend},protected;{feed_legend:hide},makeFeed',
	),

	// Unterpaletten
	'subpalettes' => array
	(
		'allowComments' => 'notify,sortOrder,perPage,moderate,bbcode,requireLogin,disableCaptcha',
		'protected'     => 'users,groups',
		'makeFeed'      => 'format,language,maxItems,feedBase,alias,modulePage,description',
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
			'sql' => "int(10) unsigned NOT NULL default '0'",
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
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['title'],
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'maxlength' => 255),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'allowComments' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['allowComments'],
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'checkbox',
			'eval'      => array('submitOnChange' => true),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'notify' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['notify'],
			'default'   => 'notify_admin',
			'exclude'   => true,
			'inputType' => 'select',
			'options'   => array('notify_admin', 'notify_author', 'notify_both'),
			'reference' => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive'],
			'sql'       => "varchar(32) NOT NULL default ''",
		),
		'sortOrder' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['sortOrder'],
			'default'   => 'ascending',
			'exclude'   => true,
			'inputType' => 'select',
			'options'   => array('ascending', 'descending'),
			'reference' => &$GLOBALS['TL_LANG']['MSC'],
			'eval'      => array('tl_class' => 'w50'),
			'sql'       => "varchar(32) NOT NULL default ''",
		),
		'perPage' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['perPage'],
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => array('rgxp' => 'natural', 'tl_class' => 'w50'),
			'sql'       => "smallint(5) unsigned NOT NULL default '0'",
		),
		'moderate' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['moderate'],
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => array('tl_class' => 'w50'),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'bbcode' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['bbcode'],
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => array('tl_class' => 'w50'),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'requireLogin' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['requireLogin'],
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => array('tl_class' => 'w50'),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'disableCaptcha' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['disableCaptcha'],
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => array('tl_class' => 'w50'),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'protected' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['protected'],
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => array('submitOnChange' => true),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'users' => array
		(
			'label'      => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['users'],
			'exclude'    => true,
			'inputType'  => 'checkbox',
			'foreignKey' => 'tl_member.username',
			'eval'       => array('multiple' => true, 'tl_class' => 'w50 cbxes'),
			'sql'        => "blob NULL",
		),
		'groups' => array
		(
			'label'      => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['groups'],
			'exclude'    => true,
			'inputType'  => 'checkbox',
			'foreignKey' => 'tl_member_group.name',
			'eval'       => array('multiple' => true, 'tl_class' => 'w50 cbxes'),
			'sql'        => "blob NULL",
		),
		'makeFeed' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['makeFeed'],
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'checkbox',
			'eval'      => array('submitOnChange' => true),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'format' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['format'],
			'default'   => 'rss',
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'select',
			'options'   => array('rss' => 'RSS 2.0', 'atom' => 'Atom'),
			'eval'      => array('tl_class' => 'w50'),
			'sql'       => "varchar(32) NOT NULL default ''",
		),
		'language' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['language'],
			'exclude'   => true,
			'search'    => true,
			'filter'    => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'maxlength' => 32, 'tl_class' => 'w50'),
			'sql'       => "varchar(32) NOT NULL default ''",
		),
		'maxItems' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['maxItems'],
			'default'   => 25,
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'rgxp' => 'natural', 'tl_class' => 'w50 clr'),
			'sql'       => "smallint(5) unsigned NOT NULL default '0'",
		),
		'feedBase' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['feedBase'],
			'default'   => Runtime::getBaseUrl(),
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'text',
			'eval'      => array('trailingSlash' => true, 'rgxp' => 'url', 'decodeEntities' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'alias' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['alias'],
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'rgxp' => 'alnum', 'unique' => true, 'spaceToUnderscore' => true, 'maxlength' => 128, 'tl_class' => 'w50 clr'),
			'sql'       => "varbinary(128) NOT NULL default ''",
		),
		'modulePage' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['modulePage'],
			'exclude'   => true,
			'inputType' => 'pageTree',
			'eval'      => array('mandatory' => true, 'fieldType' => 'radio'),
			'sql'       => "int(10) unsigned NOT NULL default '0'",
		),
		'description' => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_photoalbums2_archive']['description'],
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'textarea',
			'eval'      => array('style' => 'height:60px;', 'tl_class' => 'clr'),
			'sql'       => "text NULL",
		),
	),
);
