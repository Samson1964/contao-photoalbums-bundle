<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer;

use Contao\BackendUser;
use Contao\DataContainer;
use Contao\Image;
use Contao\StringUtil;
use Contao\System;
use Schachbulle\ContaoPhotoalbumsBundle\Model\AlbumModel;
use Schachbulle\ContaoPhotoalbumsBundle\Model\ArchiveModel;

/**
 * Rueckrufe des Datenbereichs `tl_content` fuer das Inhaltselement „Fotoalbum“.
 */
class ContentListener
{
	/**
	 * Liefert die auswaehlbaren Alben, nach Archiven gruppiert.
	 *
	 * @return array<string, array<int, string>> Archivtitel als Gruppe,
	 *                                           darunter Albumnummer und Titel
	 */
	public function getAlbums(): array
	{
		$objUser = BackendUser::getInstance();

		if (!$objUser->isAdmin && !\is_array($objUser->photoalbums2s))
		{
			return array();
		}

		$arrAlbums = array();
		$objArchives = ArchiveModel::findAll(array('order' => 'title'));

		if (null === $objArchives)
		{
			return array();
		}

		while ($objArchives->next())
		{
			if (!$objUser->isAdmin && !$objUser->hasAccess($objArchives->id, 'photoalbums2s'))
			{
				continue;
			}

			$objAlbums = AlbumModel::findBy('pid', $objArchives->id, array('order' => 'title'));

			if (null === $objAlbums)
			{
				continue;
			}

			while ($objAlbums->next())
			{
				$arrAlbums[$objArchives->title][$objAlbums->id] = $objAlbums->title;
			}
		}

		return $arrAlbums;
	}

	/**
	 * Erzeugt den Knopf „Album bearbeiten“ neben der Auswahlliste.
	 *
	 * Die Adresse wird ueber den Symfony-Router gebildet; das frueher benutzte
	 * `contao/main.php` gibt es weder unter Contao 4.13 noch unter Contao 5.
	 *
	 * @param DataContainer $dc Der Data Container
	 *
	 * @return string Das Markup des Knopfes oder eine leere Zeichenkette,
	 *                solange kein Album gewaehlt ist
	 */
	public function editAlbum(DataContainer $dc): string
	{
		$intId = (int) $dc->value;

		if ($intId < 1)
		{
			return '';
		}

		$strUrl = System::getContainer()->get('router')->generate('contao_backend', array(
			'do'    => 'photoalbums2',
			'table' => 'tl_photoalbums2_album',
			'act'   => 'edit',
			'id'    => $intId,
			'rt'    => System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue(),
		));

		$strTitle = sprintf(
			$GLOBALS['TL_LANG']['tl_photoalbums2_album']['edit'][1] ?? 'Fotoalbum ID %s bearbeiten',
			$intId
		);

		return ' <a href="'.StringUtil::specialchars($strUrl).'" title="'.StringUtil::specialchars($strTitle).'" style="padding-left:3px">'
			.Image::getHtml('alias.svg', $GLOBALS['TL_LANG']['tl_photoalbums2_album']['edit'][0] ?? '', 'style="vertical-align:top"')
			.'</a>';
	}
}
