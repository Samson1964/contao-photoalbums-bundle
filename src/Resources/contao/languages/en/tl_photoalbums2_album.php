<?php

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * Die Texte stammen in Teilen aus der Vorgängererweiterung photoalbums2
 * von Daniel Kiesel.
 *
 * @license LGPL-3.0-or-later
 */

/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['title'] = array('Title', 'Enter the title of the image album here.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['alias'] = array('Alias', 'The alias is generated automatically from the title and becomes part of the URL. It consists of lowercase letters, digits and hyphens only; umlauts are transliterated (ü becomes ue, ß becomes ss), also for a custom entry.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['author'] = array('Author', 'Here you can change the author of the album.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['startdate'] = array('Start date', 'Enter the start date here.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['enddate'] = array('End date', 'Enter an end date here when the shooting takes several days. Otherwise, leave this field empty.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['images'] = array('Images and videos', 'Choose here the images and videos to display in this album. A video gets a uniform preview tile and is played when clicked.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['previewImageType'] = array('Preview image', 'Select here to preview how the image will be displayed.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['previewImage'] = array('Select image preview', 'Choose here the preview image. Only images can be chosen — a video would show nothing but the placeholder tile.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['imageSortType'] = array('Sort images', 'Select how the images will be sorted.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['imageSort'] = array('Sort images', 'Drag the images and videos into the desired order with the mouse. The keyboard works too: click a tile and move it with Ctrl and the arrow keys. The order is applied when you save the album.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['event'] = array('Event', 'Enter here the event.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['place'] = array('Place', 'Enter here the place.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['photographer'] = array('Photographer', 'Enter here the photographer.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['description'] = array('Description', 'Enter here a description.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['protected'] = array('Protect album', 'Limit here to access this image album.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['users'] = array('Members', 'Choose the membes who have access to the image album archive.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['groups'] = array('Member groups', 'Choose the member groups who have access to the image album archive.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['cssClass'] = array('CSS class', 'Here you can enter one or more classes.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['noComments'] = array('Disable comments', 'Do not allow comments for this particular album.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['published'] = array('Publish', 'Set this checkbox to publish the image album.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['start'] = array('Show from', 'Do not show the album on the website before this day.');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['stop'] = array('Show until', 'Do not show the album on the website on and after this day.');

/**
 * Reference
 */
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['title_legend'] = 'Title';
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['date_legend'] = 'Date';
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['images_legend'] = 'Images';
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['info_legend'] = 'Meta Infos';
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['protected_legend'] = 'Protect album';
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['expert_legend'] = 'Expert settings';
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['published_legend'] = 'Publish';

/**
 * Buttons
 */
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['new']    = array('Create new image album', 'Create a new image album');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['edit']   = array('Edit image album', 'Edit the image album ID %s');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['copy']   = array('Copy image album', 'Copy image album ID %s');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['cut']   = array('Cut image album', 'Cut image album ID %s');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['delete'] = array('Delete image album', 'Delete image album ID %s');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['show']   = array('Show details of the image album', 'Show details of the image album ID %s');
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['toggle'] = array('Publish/unpublish image album', 'Publish/unpublish image album ID %s');

/**
 * Target page shown in the album list
 */
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['urlTarget'] = 'Links point to: %s';
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['urlNone'] = 'No published page shows this album — links to it remain empty. Set the "Page with the image view" in the archive.';
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['urlSource']['archive'] = 'archive setting';
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['urlSource']['detailPage'] = 'detail page of module "%s"';
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['urlSource']['reader'] = 'page of module "%s"';
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['urlSource']['onePage'] = 'page of module "%s"';
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['urlSource']['contentElement'] = 'content element showing this album';
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['urlSource']['albumView'] = 'page of module "%s"';
$GLOBALS['TL_LANG']['tl_photoalbums2_album']['aliasEmpty'] = 'The alias contains no usable characters. Letters, digits and hyphens are allowed.';
