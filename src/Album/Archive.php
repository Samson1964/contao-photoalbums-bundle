<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Album;

use Contao\FrontendUser;
use Contao\Model\Collection;
use Contao\StringUtil;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Runtime;
use Schachbulle\ContaoPhotoalbumsBundle\Model\AlbumModel;
use Schachbulle\ContaoPhotoalbumsBundle\Model\ArchiveModel;
use Schachbulle\ContaoPhotoalbumsBundle\Sorter\AlbumSorter;

/**
 * Arbeitet mit einer Auswahl von Fotoalben-Archiven.
 *
 * Die Klasse hat zwei Aufgaben: Sie wirft geschuetzte Archive aus der Liste,
 * auf die der angemeldete Besucher keinen Zugriff hat, und sie liefert zu den
 * verbleibenden Archiven die zugehoerigen Alben — bereits in der im Modul
 * eingestellten Reihenfolge.
 */
class Archive extends ItemList
{
	/**
	 * Entfernt geschuetzte Archive ohne Zugriffsrecht aus der Liste.
	 *
	 * Ein Archiv gilt als zugaenglich, wenn es nicht geschuetzt ist oder wenn
	 * das angemeldete Mitglied entweder selbst freigeschaltet ist oder einer
	 * freigeschalteten Mitgliedergruppe angehoert.
	 *
	 * @return void Schreibt die bereinigte Liste zurueck nach $this->items
	 */
	protected function sortOut(): void
	{
		if (empty($this->items))
		{
			return;
		}

		$objItems = ArchiveModel::findMultipleByIds($this->items);
		$arrItems = array();

		if (null !== $objItems)
		{
			while ($objItems->next())
			{
				if ($objItems->protected && !$this->hasAccess($objItems->users, $objItems->groups))
				{
					continue;
				}

				$arrItems[] = $objItems->id;
			}
		}

		$this->items = $arrItems;
	}

	/**
	 * Prueft den Zugriff des angemeldeten Mitglieds auf ein geschuetztes Archiv.
	 *
	 * @param mixed $varUsers  Serialisiertes Feld der freigeschalteten Mitglieder
	 * @param mixed $varGroups Serialisiertes Feld der freigeschalteten Gruppen
	 *
	 * @return bool true, wenn der Zugriff erlaubt ist; false auch dann, wenn
	 *              ueberhaupt niemand angemeldet ist
	 */
	private function hasAccess($varUsers, $varGroups): bool
	{
		if (!Runtime::hasFrontendUser())
		{
			return false;
		}

		$arrUsers = StringUtil::deserialize($varUsers, true);
		$arrGroups = StringUtil::deserialize($varGroups, true);

		$objUser = FrontendUser::getInstance();
		$arrUserGroups = StringUtil::deserialize($objUser->groups, true);

		if (!empty($arrUsers) && \in_array($objUser->id, $arrUsers))
		{
			return true;
		}

		if (!empty($arrGroups) && \count(array_intersect($arrGroups, $arrUserGroups)) > 0)
		{
			return true;
		}

		return false;
	}

	/**
	 * Liefert die Nummern der zugaenglichen Archive.
	 *
	 * @return array<int, int|string> Die bereinigte Nummernliste
	 */
	public function getArchiveIds(): array
	{
		return $this->items;
	}

	/**
	 * Liefert die zugaenglichen Archive als Datensatzsammlung.
	 *
	 * @return Collection|null Die Archive oder null, wenn keines uebrig ist
	 */
	public function getArchives(): ?Collection
	{
		if (empty($this->items))
		{
			return null;
		}

		return ArchiveModel::findMultipleByIds($this->items);
	}

	/**
	 * Liefert die Nummern aller Alben aus den zugaenglichen Archiven.
	 *
	 * Die Liste wird zunaechst nach der im Modul gewaehlten Albensortierung
	 * geordnet und danach durch {@see Album} noch einmal um alles bereinigt,
	 * was durch den Zugriffsschutz oder den Zeitfilter faellt.
	 *
	 * @return array<int, int|string>|null Die Albumnummern oder null, wenn es
	 *                                     keine gibt
	 */
	public function getAlbumIds(): ?array
	{
		$arrAlbumIds = array();
		$objAlbums = AlbumModel::findAlbumsByMultipleArchives($this->items);

		if (null === $objAlbums)
		{
			return null;
		}

		while ($objAlbums->next())
		{
			$arrAlbumIds[] = $objAlbums->id;
		}

		if (isset($this->pa2AlbumSortType))
		{
			$objAlbumSorter = new AlbumSorter(
				$this->pa2AlbumSortType,
				$arrAlbumIds,
				StringUtil::deserialize($this->pa2AlbumSort, true)
			);

			$arrAlbumIds = $objAlbumSorter->getSortedIds();
		}

		$objAlbum = new Album($arrAlbumIds, $this->getData());

		return $objAlbum->getAlbumIds();
	}

	/**
	 * Liefert die Alben der zugaenglichen Archive als Datensatzsammlung.
	 *
	 * @return Collection|null Die Alben oder null, wenn es keine gibt
	 */
	public function getAlbums(): ?Collection
	{
		$objAlbum = new Album($this->getAlbumIds(), $this->getData());

		return $objAlbum->getAlbums();
	}
}
