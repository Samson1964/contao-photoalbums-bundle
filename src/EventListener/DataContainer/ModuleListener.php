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
use Contao\Database;
use Contao\DataContainer;
use Contao\ModuleModel;
use Contao\StringUtil;
use Schachbulle\ContaoPhotoalbumsBundle\Album\Archive;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Palette;
use Schachbulle\ContaoPhotoalbumsBundle\Model\ArchiveModel;

/**
 * Rueckrufe des Datenbereichs `tl_module` fuer die Fotoalben-Module.
 */
class ModuleListener
{
	/**
	 * Liefert die Archive, auf die der angemeldete Benutzer zugreifen darf.
	 *
	 * @return array<int, string> Nummer und Titel der Archive
	 */
	public function getArchives(): array
	{
		$objUser = BackendUser::getInstance();

		if (!$objUser->isAdmin && !\is_array($objUser->photoalbums2s))
		{
			return array();
		}

		$arrArchives = array();
		$objArchives = ArchiveModel::findAll(array('order' => 'title'));

		if (null !== $objArchives)
		{
			while ($objArchives->next())
			{
				if ($objUser->isAdmin || $objUser->hasAccess($objArchives->id, 'photoalbums2s'))
				{
					$arrArchives[$objArchives->id] = $objArchives->title;
				}
			}
		}

		return $arrArchives;
	}

	/**
	 * Liefert die Alben der gewaehlten Archive fuer den Sortier-Assistenten.
	 *
	 * Gelesen wird das Feld `pa2Archives` aus der Datenbank statt aus
	 * `$dc->activeRecord`: Letzteres gilt ab Contao 5 als veraltet, und zum
	 * Zeitpunkt des Options-Rueckrufs steht der Datensatz ohnehin geschrieben
	 * in der Tabelle.
	 *
	 * @param DataContainer $dc Der Data Container
	 *
	 * @return array<int, string> Albumnummer und Beschriftung „Album (Archiv)“
	 */
	public function getAlbumSort(DataContainer $dc): array
	{
		$intId = (int) $dc->id;

		if ($intId < 1)
		{
			return array();
		}

		$objRecord = Database::getInstance()
			->prepare('SELECT pa2Archives FROM tl_module WHERE id=?')
			->limit(1)
			->execute($intId);

		if ($objRecord->numRows < 1)
		{
			return array();
		}

		$objArchive = new Archive(StringUtil::deserialize($objRecord->pa2Archives, true), array());

		$arrArchiveTitles = array();
		$objArchives = $objArchive->getArchives();

		if (null !== $objArchives)
		{
			while ($objArchives->next())
			{
				$arrArchiveTitles[$objArchives->id] = $objArchives->title;
			}
		}

		$arrAlbums = array();
		$objAlbums = $objArchive->getAlbums();

		if (null !== $objAlbums)
		{
			while ($objAlbums->next())
			{
				$strArchive = $arrArchiveTitles[$objAlbums->pid] ?? '';
				$arrAlbums[$objAlbums->id] = $objAlbums->title.('' !== $strArchive ? ' ('.$strArchive.')' : '');
			}
		}

		return $arrAlbums;
	}

	/**
	 * Haelt den Ansichtsmodus der Module „Liste“ und „Leser“ stimmig.
	 *
	 * Beide Module arbeiten immer mit getrennten Seiten, haben das Feld
	 * `pa2Mode` aber gar nicht in ihrer Palette. Damit ein spaeterer Wechsel
	 * des Modultyps nicht auf einem unpassenden Modus sitzen bleibt, wird der
	 * Wert hier mitgefuehrt.
	 *
	 * @param DataContainer $dc Der Data Container
	 *
	 * @return void
	 */
	public function handleListAndViewModule(DataContainer $dc): void
	{
		$objModule = ModuleModel::findByPk($dc->id);

		if (null === $objModule)
		{
			return;
		}

		switch ($objModule->type)
		{
			case 'photoalbums2list':
				$objModule->pa2Mode = 'pa2_with_detail_page';
				$objModule->pa2OverviewPage = '';
				break;

			case 'photoalbums2view':
				$objModule->pa2Mode = 'pa2_with_detail_page';
				$objModule->pa2DetailPage = '';
				break;

			default:
				return;
		}

		$objModule->save();
	}

	/**
	 * Setzt die Palette des Moduls „Fotoalbum“ auf den gewaehlten Modus.
	 *
	 * Contao waehlt die Palette allein anhand des Modultyps. Die drei
	 * Ansichtsmodi brauchen aber unterschiedliche Felder, deshalb wird die
	 * passende Palette hier unter den Modulnamen kopiert.
	 *
	 * @param DataContainer $dc Der Data Container
	 *
	 * @return void
	 */
	public function fixPalette(DataContainer $dc): void
	{
		$objModule = ModuleModel::findByPk($dc->id);
		$strMode = null !== $objModule ? (string) $objModule->pa2Mode : '';

		if (!isset($GLOBALS['TL_DCA']['tl_module']['palettes'][$strMode]) || 0 !== strncmp($strMode, 'pa2_', 4))
		{
			$strMode = 'pa2_on_one_page';
		}

		$GLOBALS['TL_DCA']['tl_module']['palettes']['photoalbums2'] = $GLOBALS['TL_DCA']['tl_module']['palettes'][$strMode];

		// In der reinen Album-Ansicht steht je Zeile nur ein Feld, deshalb
		// bekommt jedes einen Zeilenumbruch
		if ('pa2_only_album_view' === $strMode)
		{
			$arrFields = array(
				'pa2AlbumsTemplate',
				'pa2NumberOfAlbums',
				'pa2AlbumsPerPage',
				'pa2AlbumsPerRow',
				'pa2AlbumsShowHeadline',
				'pa2AlbumsShowTitle',
				'pa2AlbumsShowTeaser',
				'pa2AlbumsImageSize',
				'pa2AlbumsImageMargin',
				'pa2AlbumsShowMetaDescriptions',
			);

			foreach ($arrFields as $strField)
			{
				$GLOBALS['TL_DCA']['tl_module']['fields'][$strField]['eval']['tl_class'] = 'w50 clr';
			}

			$GLOBALS['TL_DCA']['tl_module']['fields']['pa2AlbumsMetaFields']['eval']['tl_class'] = 'w50 cbxes clr';
		}

		// Der Sortier-Assistent erscheint nur bei eigener Sortierung
		if (null === $objModule || 'custom' !== $objModule->pa2AlbumSortType)
		{
			Palette::removeField('tl_module', 'photoalbums2', 'pa2AlbumSort');
			Palette::removeField('tl_module', 'photoalbums2list', 'pa2AlbumSort');
		}
	}
}
