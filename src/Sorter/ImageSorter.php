<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Sorter;

/**
 * Bringt die Fotos eines Albums in die im Backend gewaehlte Reihenfolge.
 *
 * Die Klasse ist die Bruecke zwischen dem Feldwert aus `imageSortType`
 * (etwa `name_asc` oder `custom`) und dem {@see FileSorter}, der die
 * eigentliche Arbeit macht.
 */
class ImageSorter
{
	/**
	 * Das Sortierkriterium aus dem Feld `imageSortType`.
	 *
	 * @var string
	 */
	private $strSortKey;

	/**
	 * Die im Album ausgewaehlten Dateien und Ordner.
	 *
	 * @var array<int, mixed>
	 */
	private $arrUuids;

	/**
	 * Die im Sortier-Assistenten festgelegte eigene Reihenfolge.
	 *
	 * @var array<int, mixed>
	 */
	private $arrCustomUuids;

	/**
	 * @param mixed $strSortKey     Sortierkriterium, etwa `name_asc`,
	 *                              `date_desc`, `random` oder `custom`;
	 *                              ein leerer Wert liefert spaeter die
	 *                              unsortierte Liste
	 * @param mixed $arrUuids       Feld mit UUIDs von Dateien und Ordnern
	 * @param mixed $arrCustomUuids Feld mit der eigenen Reihenfolge; ist es
	 *                              kein Feld, wird $arrUuids benutzt
	 */
	public function __construct($strSortKey, $arrUuids, $arrCustomUuids)
	{
		$this->strSortKey = \is_string($strSortKey) ? $strSortKey : '';
		$this->arrUuids = \is_array($arrUuids) ? $arrUuids : array();
		$this->arrCustomUuids = \is_array($arrCustomUuids) ? $arrCustomUuids : $this->arrUuids;
	}

	/**
	 * Liefert die sortierten Datei-UUIDs des Albums.
	 *
	 * Das Kriterium traegt die Richtung als Endung (`_asc`/`_desc`); bei
	 * `custom` wird stattdessen die im Assistenten festgelegte Reihenfolge als
	 * Ausgangsliste genommen. Ordner in der Auswahl werden dabei vom
	 * {@see FileSorter} rekursiv aufgeloest.
	 *
	 * @return array<int, string> Die UUIDs der Fotos in Ausgabereihenfolge
	 */
	public function getSortedUuids(): array
	{
		$arrUuids = $this->arrUuids;
		$strSortKey = $this->strSortKey;
		$strSortDirection = 'ASC';

		if (preg_match('#^([^_]*)_([a-zA-Z]{3,4})$#', $this->strSortKey, $arrMatches))
		{
			$strSortKey = $arrMatches[1];
			$strSortDirection = $arrMatches[2];
		}
		elseif ('custom' === $this->strSortKey)
		{
			$arrUuids = $this->arrCustomUuids;
		}

		// Die Endungsliste haelt Fremdes aus einem mitausgewaehlten Ordner
		// heraus — dort koennen neben Fotos und Videos auch PDF-Dateien oder
		// Textdateien liegen, die als Kachel nur eine Luecke ergaeben
		$objFileSorter = new FileSorter($arrUuids, $GLOBALS['pa2']['mediaExtensions'] ?? null);
		$objFileSorter->sortImagesBy($strSortKey, $strSortDirection);

		return $objFileSorter->getImageUuids();
	}
}
