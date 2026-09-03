<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Sorter;

use Contao\StringUtil;
use Schachbulle\ContaoPhotoalbumsBundle\Model\AlbumModel;

/**
 * Bringt die Alben einer Uebersicht in die im Modul gewaehlte Reihenfolge.
 *
 * Sortiert wird nicht in der Datenbank, sondern auf der bereits ermittelten
 * Nummernliste. Das ist noetig, weil die Liste zu diesem Zeitpunkt schon durch
 * den Zugriffsschutz und den Zeitfilter gegangen ist und weil die Reihenfolge
 * `custom` gar nicht aus einer Spalte kommt, sondern aus dem Sortier-Assistenten
 * des Moduls.
 */
class AlbumSorter
{
	/**
	 * Das Sortierkriterium aus dem Feld `pa2AlbumSortType`.
	 *
	 * @var string
	 */
	private $strSortKey;

	/**
	 * Die zu sortierenden Albumnummern.
	 *
	 * @var array<int, int|string>
	 */
	private $arrIds;

	/**
	 * Die im Assistenten festgelegte eigene Reihenfolge.
	 *
	 * @var array<int, int|string>
	 */
	private $arrCustomIds;

	/**
	 * @param mixed $strSortKey   Sortierkriterium, etwa `title_asc`,
	 *                            `startdate_desc`, `random` oder `custom`
	 * @param mixed $arrIds       Feld mit Albumnummern
	 * @param mixed $arrCustomIds Feld mit der eigenen Reihenfolge; ist es kein
	 *                            Feld, wird $arrIds benutzt
	 */
	public function __construct($strSortKey, $arrIds, $arrCustomIds)
	{
		$this->strSortKey = \is_string($strSortKey) ? $strSortKey : '';
		$this->arrIds = \is_array($arrIds) ? $arrIds : array();
		$this->arrCustomIds = \is_array($arrCustomIds) ? $arrCustomIds : $this->arrIds;
	}

	/**
	 * Liefert die sortierten Albumnummern.
	 *
	 * @return array<int, int|string> Die Nummern in Ausgabereihenfolge; ohne
	 *                                gesetztes Kriterium unveraendert
	 */
	public function getSortedIds(): array
	{
		if ('' === $this->strSortKey)
		{
			return $this->arrIds;
		}

		$strSortKey = $this->strSortKey;
		$strSortDirection = 'ASC';

		if (preg_match('#^([^_]*)_([a-zA-Z]{3,4})$#', $this->strSortKey, $arrMatches))
		{
			$strSortKey = $arrMatches[1];
			$strSortDirection = $arrMatches[2];
		}

		$this->sortBy($strSortKey, $strSortDirection);

		return $this->arrIds;
	}

	/**
	 * Fuehrt die Sortierung durch und schreibt das Ergebnis nach $arrIds.
	 *
	 * Bei `custom` wird die Reihenfolge des Assistenten vorangestellt und alles
	 * dahinter angehaengt, was der Assistent noch nicht kennt — sonst wuerden
	 * neu angelegte Alben aus der Ausgabe verschwinden.
	 *
	 * Die Datumssortierung nutzt ausdruecklich SORT_NUMERIC, damit auch
	 * **negative** Zeitstempel (Aufnahmen vor 1970) richtig einsortiert werden.
	 *
	 * @param string $strSortKey       Kriterium ohne Richtungsendung
	 * @param string $strSortDirection `ASC` oder `DESC`
	 *
	 * @return bool true, wenn sortiert wurde; false bei leerer Liste
	 */
	private function sortBy(string $strSortKey, string $strSortDirection = 'ASC'): bool
	{
		if (empty($this->arrIds))
		{
			return false;
		}

		$strSortKey = strtolower($strSortKey);
		$strSortDirection = strtoupper($strSortDirection);

		if ('custom' === $strSortKey)
		{
			$arrIds = array_intersect($this->arrCustomIds, $this->arrIds);
			$arrIds = array_merge($arrIds, $this->arrIds);

			$this->arrIds = array_values(array_unique($arrIds));
		}
		elseif ('random' === $strSortKey)
		{
			shuffle($this->arrIds);
		}
		else
		{
			$arrSort = array();
			$sortType = SORT_STRING;

			foreach ($this->arrIds as $intId)
			{
				$objAlbum = AlbumModel::findByPk($intId);

				if (null === $objAlbum)
				{
					continue;
				}

				switch ($strSortKey)
				{
					case 'title':
						$sortType = SORT_STRING;
						$arrSort[$objAlbum->id] = (string) $objAlbum->title;
						break;

					case 'startdate':
						$sortType = SORT_NUMERIC;
						$arrSort[$objAlbum->id] = '' === (string) $objAlbum->startdate ? 0 : (int) $objAlbum->startdate;
						break;

					case 'enddate':
						$sortType = SORT_NUMERIC;
						$arrSort[$objAlbum->id] = '' === (string) $objAlbum->enddate ? 0 : (int) $objAlbum->enddate;
						break;

					case 'numberofimages':
						$sortType = SORT_NUMERIC;
						$arrSort[$objAlbum->id] = \count(StringUtil::deserialize($objAlbum->images, true));
						break;

					default:
						// Unbekanntes Kriterium: Reihenfolge unveraendert lassen
						return false;
				}
			}

			asort($arrSort, $sortType);
			$this->arrIds = array_keys($arrSort);
		}

		if ('DESC' === $strSortDirection)
		{
			$this->arrIds = array_reverse($this->arrIds);
		}

		return true;
	}
}
