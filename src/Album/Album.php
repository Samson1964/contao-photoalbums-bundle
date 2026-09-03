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
use Schachbulle\ContaoPhotoalbumsBundle\Helper\TimeFilter;
use Schachbulle\ContaoPhotoalbumsBundle\Model\AlbumModel;
use Schachbulle\ContaoPhotoalbumsBundle\Sorter\ImageSorter;

/**
 * Arbeitet mit einer Auswahl von Fotoalben.
 *
 * Die Klasse wirft aus der Liste alles heraus, was der Besucher nicht sehen
 * darf — unveroeffentlichte Alben, geschuetzte Alben ohne Zugriffsrecht, Alben
 * aus einem gesperrten Archiv und Alben ausserhalb des Zeitfilters. Zu den
 * verbleibenden Alben liefert sie die fertig aufbereiteten Datensaetze samt
 * sortierter Fotoliste und Vorschaubild.
 */
class Album extends ItemList
{
	/**
	 * @param mixed                $varValue Albumnummer, Feld von Nummern oder
	 *                                       ein Alias; ein Alias wird sofort in
	 *                                       die zugehoerige Nummer uebersetzt
	 * @param array<string, mixed> $arrData  Die Daten des aufrufenden Moduls
	 */
	public function __construct($varValue, $arrData)
	{
		if (!\is_array($varValue) && !is_numeric($varValue))
		{
			$varValue = $this->getIdByAlias((string) $varValue);
		}

		parent::__construct($varValue, $arrData);
	}

	/**
	 * Uebersetzt einen Album-Alias in die Datensatznummer.
	 *
	 * @param string $strAlias Der Alias aus der Adresse
	 *
	 * @return int|string Die Nummer des Albums oder — wenn kein
	 *                    veroeffentlichtes Album dazu existiert — der
	 *                    unveraenderte Alias, damit der Aufrufer die leere
	 *                    Ausgabe erzeugen kann
	 */
	private function getIdByAlias(string $strAlias)
	{
		$objAlbum = AlbumModel::findPublishedByIdOrAlias($strAlias);

		if (null !== $objAlbum && $objAlbum->count() > 0)
		{
			$intId = (int) $objAlbum->current()->id;

			return $intId > 0 ? $intId : 0;
		}

		return $strAlias;
	}

	/**
	 * Entfernt alle Alben aus der Liste, die im Frontend nicht sichtbar sind.
	 *
	 * Im Backend bleibt die Liste unangetastet: Dort soll die Vorschau eines
	 * Inhaltselements auch ein noch unveroeffentlichtes Album zeigen.
	 *
	 * @return void Schreibt die bereinigte Liste zurueck nach $this->items
	 */
	protected function sortOut(): void
	{
		if (empty($this->items))
		{
			return;
		}

		$objItems = AlbumModel::findMultipleByIds($this->items);
		$arrItems = array();

		if (null !== $objItems)
		{
			while ($objItems->next())
			{
				if (Runtime::isFrontend())
				{
					if (1 != $objItems->published)
					{
						continue;
					}

					if (!$this->hasAccess($objItems->current()))
					{
						continue;
					}
				}

				$arrItems[] = $objItems->id;
			}
		}

		$this->items = $arrItems;
	}

	/**
	 * Prueft, ob ein einzelnes Album im Frontend ausgegeben werden darf.
	 *
	 * Geprueft werden nacheinander der Zugriffsschutz des Albums selbst, der
	 * Zugriffsschutz des uebergeordneten Archivs und zuletzt der Zeitfilter des
	 * aufrufenden Moduls.
	 *
	 * @param AlbumModel|null $objAlbum Der Albumdatensatz
	 *
	 * @return bool true, wenn das Album ausgegeben werden darf
	 */
	private function hasAccess($objAlbum): bool
	{
		if (!$objAlbum instanceof AlbumModel)
		{
			return false;
		}

		if ($objAlbum->protected && !$this->hasMemberAccess($objAlbum->users, $objAlbum->groups))
		{
			return false;
		}

		// Das Archiv darf nicht gesperrt sein, sonst waere das Album ueber den
		// Umweg der Albumnummer trotz gesperrtem Archiv erreichbar
		$objArchive = new Archive($objAlbum->pid, $this->getData());
		$arrArchiveIds = $objArchive->getArchiveIds();

		if (empty($arrArchiveIds) || !\in_array($objAlbum->pid, $arrArchiveIds))
		{
			return false;
		}

		if ($this->pa2TimeFilter)
		{
			$objTimeFilter = new TimeFilter($this->pa2TimeFilterStart, $this->pa2TimeFilterEnd);

			if ($objTimeFilter->doFilter($objAlbum->startdate, $objAlbum->enddate))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Prueft den Zugriff des angemeldeten Mitglieds auf ein geschuetztes Album.
	 *
	 * @param mixed $varUsers  Serialisiertes Feld der freigeschalteten Mitglieder
	 * @param mixed $varGroups Serialisiertes Feld der freigeschalteten Gruppen
	 *
	 * @return bool true, wenn der Zugriff erlaubt ist
	 */
	private function hasMemberAccess($varUsers, $varGroups): bool
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
	 * Liefert die Nummern der sichtbaren Alben.
	 *
	 * @return array<int, int|string> Die bereinigte Nummernliste
	 */
	public function getAlbumIds(): array
	{
		return $this->items;
	}

	/**
	 * Liefert die sichtbaren Alben als aufbereitete Datensatzsammlung.
	 *
	 * Jeder Datensatz bekommt dabei drei zusaetzliche Eigenschaften:
	 * `images` und `imageSort` liegen deserialisiert vor, `objPreviewImage`
	 * traegt den Dateidatensatz des Vorschaubildes und `arrSortedImageUuids`
	 * die fertig sortierte Liste aller Fotos des Albums.
	 *
	 * @return Collection|null Die Alben oder null, wenn keines sichtbar ist
	 */
	public function getAlbums(): ?Collection
	{
		if (empty($this->items))
		{
			return null;
		}

		$objAlbum = AlbumModel::findMultipleByIds($this->items);

		if (null === $objAlbum)
		{
			return null;
		}

		while ($objAlbum->next())
		{
			$objImage = new Image($objAlbum->previewImage);
			$objAlbum->objPreviewImage = $objImage->getFile();

			$objAlbum->images = StringUtil::deserialize($objAlbum->images, true);
			$objAlbum->imageSort = StringUtil::deserialize($objAlbum->imageSort, true);

			$objImageSorter = new ImageSorter($objAlbum->imageSortType, $objAlbum->images, $objAlbum->imageSort);
			$objAlbum->arrSortedImageUuids = $objImageSorter->getSortedUuids();
		}

		$objAlbum->reset();

		return $objAlbum;
	}
}
