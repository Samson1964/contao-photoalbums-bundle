<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Helper;

use Contao\Input;
use Contao\Pagination as ContaoPagination;

/**
 * Zerteilt eine fertige Liste in Seiten und baut das Seitenmenü dazu.
 *
 * Anders als bei den Kernmodulen von Contao wird hier nicht in der Datenbank
 * limitiert: Die Liste der Alben beziehungsweise Fotos steht zu diesem
 * Zeitpunkt schon fest, weil sie durch Zugriffsschutz, Zeitfilter und eine
 * eigene Sortierung gegangen ist. Der Seitenumbruch arbeitet deshalb auf dem
 * fertigen Feld.
 */
class Pagination
{
	/**
	 * Die Einträge der aktuellen Seite.
	 *
	 * @var array<int, mixed>
	 */
	private $arrItems = array();

	/**
	 * Gesamtzahl der Einträge nach Anwendung der Höchstzahl.
	 *
	 * @var int
	 */
	private $intTotalItems = 0;

	/**
	 * Das fertige Markup des Seitenmenüs.
	 *
	 * @var string
	 */
	private $strPagination = '';

	/**
	 * @param mixed $arrItems        Feld der Einträge (Albumnummern oder
	 *                               Datei-UUIDs)
	 * @param mixed $intMaxItems     Höchstzahl insgesamt; 0 bedeutet „alle“
	 * @param mixed $intItemsPerPage Einträge je Seite; 0 schaltet den
	 *                               Seitenumbruch ab
	 */
	public function __construct($arrItems, $intMaxItems = 0, $intItemsPerPage = 0)
	{
		if (!\is_array($arrItems) || empty($arrItems))
		{
			return;
		}

		$this->arrItems = array_values($arrItems);
		$this->intTotalItems = \count($this->arrItems);

		$this->compile((int) $intMaxItems, (int) $intItemsPerPage);
	}

	/**
	 * Schneidet die Liste auf die aktuelle Seite zu.
	 *
	 * @param int $intMaxItems     Höchstzahl insgesamt; 0 bedeutet „alle“
	 * @param int $intItemsPerPage Einträge je Seite; 0 schaltet den
	 *                             Seitenumbruch ab
	 *
	 * @return void Setzt $arrItems, $intTotalItems und $strPagination
	 */
	private function compile(int $intMaxItems, int $intItemsPerPage): void
	{
		$intPage = (int) Input::get('page');
		$intPage = $intPage > 0 ? $intPage : 1;

		$intLimit = 0;

		if ($intMaxItems > 0)
		{
			$intLimit = $intMaxItems;
			$this->intTotalItems = min($intMaxItems, $this->intTotalItems);
		}

		// Ohne Seitengröße oder wenn die Höchstzahl kleiner als eine Seite
		// ist, bleibt es bei einer einzigen Seite
		if ($intItemsPerPage < 1 || (0 !== $intLimit && $intMaxItems <= $intItemsPerPage))
		{
			if ($intLimit > 0)
			{
				$this->arrItems = \array_slice($this->arrItems, 0, $intLimit);
			}

			return;
		}

		// Seitennummer auf die letzte vorhandene Seite begrenzen
		$intMaxPage = (int) ceil($this->intTotalItems / $intItemsPerPage);

		if ($intMaxPage > 0 && $intPage > $intMaxPage)
		{
			$intPage = $intMaxPage;
		}

		$intOffset = (max($intPage, 1) - 1) * $intItemsPerPage;
		$intLength = $intItemsPerPage;

		if ($intOffset + $intLength > $this->intTotalItems)
		{
			$intLength = $this->intTotalItems - $intOffset;
		}

		$objPagination = new ContaoPagination($this->intTotalItems, $intItemsPerPage);
		$this->strPagination = $objPagination->generate("\n  ");

		$this->arrItems = \array_slice($this->arrItems, $intOffset, max($intLength, 0));
	}

	/**
	 * Liefert die Einträge der aktuellen Seite.
	 *
	 * @return array<int, mixed> Die Einträge; leer, wenn es keine gibt
	 */
	public function getItems(): array
	{
		return $this->arrItems;
	}

	/**
	 * Liefert die Gesamtzahl der Einträge.
	 *
	 * @return int Die Zahl nach Anwendung der Höchstzahl, aber vor dem
	 *             Seitenumbruch
	 */
	public function getTotalItems(): int
	{
		return $this->intTotalItems;
	}

	/**
	 * Liefert das Markup des Seitenmenüs.
	 *
	 * @return string Das Markup oder eine leere Zeichenkette, wenn kein
	 *                Seitenumbruch nötig ist
	 */
	public function getPagination(): string
	{
		return $this->strPagination;
	}
}
