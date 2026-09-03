<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Sorter;

use Contao\FilesModel;
use Contao\StringUtil;

/**
 * Loest eine Auswahl aus Dateien und Ordnern in eine sortierte Bilderliste auf.
 *
 * Diese Klasse stammt urspruenglich aus der Erweiterung
 * `craffft/contao-imagesortwizard` und ist hier fest eingebaut, damit das
 * Bundle ohne Fremdabhaengigkeiten auskommt.
 *
 * Zwei Aufgaben stecken darin: Erstens werden ausgewaehlte **Ordner**
 * rekursiv durchlaufen, so dass am Ende nur noch Dateien in der Liste stehen.
 * Zweitens laesst sich diese Liste nach Meta-Titel, Dateiname oder
 * Aenderungsdatum sortieren.
 */
class FileSorter
{
	/**
	 * Die aufgeloesten Datei-UUIDs in ihrer aktuellen Reihenfolge.
	 *
	 * @var array<int, string>
	 */
	private $arrUuids = array();

	/**
	 * Die zugelassenen Dateiendungen, klein geschrieben.
	 *
	 * Ein leeres Feld bedeutet: Es wird nicht nach Endung gefiltert.
	 *
	 * @var array<int, string>
	 */
	private $arrExtensions = array();

	/**
	 * Nimmt die Auswahl entgegen und loest sie sofort auf.
	 *
	 * @param mixed       $arrUuids      Feld mit UUIDs von Dateien und/oder
	 *                                   Ordnern; alles andere ergibt eine leere
	 *                                   Liste
	 * @param string|null $strExtensions Kommagetrennte Liste zugelassener
	 *                                   Dateiendungen, etwa "png,jpg,jpeg,gif,webp";
	 *                                   null laesst alle Dateien zu
	 */
	public function __construct($arrUuids, ?string $strExtensions = null)
	{
		$this->setExtensions($strExtensions);

		if (\is_array($arrUuids))
		{
			$this->setAllImageUuids($arrUuids);
		}
	}

	/**
	 * Zerlegt die Endungsliste in ein Feld.
	 *
	 * @param string|null $strExtensions Kommagetrennte Endungen oder null
	 *
	 * @return void Setzt ausschliesslich die Eigenschaft $arrExtensions
	 */
	private function setExtensions(?string $strExtensions): void
	{
		$this->arrExtensions = array();

		if (null !== $strExtensions && '' !== $strExtensions)
		{
			$this->arrExtensions = array_map('strtolower', array_map('trim', explode(',', $strExtensions)));
		}
	}

	/**
	 * Loest jede uebergebene UUID auf und sammelt die gefundenen Dateien.
	 *
	 * @param array<int, mixed> $arrUuids Die Auswahl aus dem Dateibaum
	 *
	 * @return void Setzt ausschliesslich die Eigenschaft $arrUuids
	 */
	private function setAllImageUuids(array $arrUuids): void
	{
		$arrAllUuids = array();

		foreach ($arrUuids as $uuid)
		{
			$arrAllUuids = array_merge($arrAllUuids, $this->scanDirRecursive($uuid));
		}

		$this->arrUuids = array_values(array_unique($arrAllUuids));
	}

	/**
	 * Steigt in einen Ordner hinab oder nimmt eine Datei auf.
	 *
	 * @param mixed $uuid Die UUID eines Eintrags aus tl_files
	 *
	 * @return array<int, string> Die UUIDs aller gefundenen Dateien; leer, wenn
	 *                            der Eintrag fehlt oder keine zugelassene
	 *                            Endung hat
	 */
	private function scanDirRecursive($uuid): array
	{
		$arrUuids = array();
		$objFile = FilesModel::findByUuid($uuid);

		if (null === $objFile)
		{
			return $arrUuids;
		}

		switch ($objFile->type)
		{
			case 'folder':
				$objChildren = FilesModel::findByPid($uuid);

				if (null !== $objChildren)
				{
					while ($objChildren->next())
					{
						$arrScan = $this->scanDirRecursive($objChildren->uuid);

						if (!empty($arrScan))
						{
							$arrUuids = array_merge($arrUuids, $arrScan);
						}
					}
				}
				break;

			case 'file':
				// Ohne Endungsfilter zaehlt jede Datei, sonst nur die passenden
				if (empty($this->arrExtensions) || \in_array(strtolower((string) $objFile->extension), $this->arrExtensions, true))
				{
					$arrUuids[] = $objFile->uuid;
				}
				break;
		}

		return array_values(array_unique($arrUuids));
	}

	/**
	 * Sortiert die aufgeloeste Dateiliste.
	 *
	 * @param string $strSortKey       Sortierkriterium: `metatitle`, `name`,
	 *                                 `date`, `random` oder `custom`. Bei
	 *                                 `custom` bleibt die Reihenfolge, wie sie
	 *                                 uebergeben wurde
	 * @param string $strSortDirection `ASC` oder `DESC`; bei `DESC` wird die
	 *                                 fertige Liste am Ende umgedreht
	 *
	 * @return bool true, wenn sortiert wurde; false, wenn gar keine Dateien
	 *              vorliegen
	 */
	public function sortImagesBy(string $strSortKey, string $strSortDirection = 'ASC'): bool
	{
		if (empty($this->arrUuids))
		{
			return false;
		}

		$strSortKey = strtolower($strSortKey);
		$strSortDirection = strtoupper($strSortDirection);

		if ('custom' === $strSortKey)
		{
			// Die uebergebene Reihenfolge ist bereits die gewuenschte
		}
		elseif ('random' === $strSortKey)
		{
			shuffle($this->arrUuids);
		}
		else
		{
			$arrSort = array();
			$sortType = SORT_STRING;

			foreach ($this->arrUuids as $uuid)
			{
				$objFile = FilesModel::findByUuid($uuid);

				if (null === $objFile)
				{
					continue;
				}

				switch ($strSortKey)
				{
					case 'metatitle':
						$sortType = SORT_STRING;
						$arrSort[$objFile->uuid] = $this->getMetaTitle($objFile);
						break;

					case 'name':
						$sortType = SORT_STRING;
						$arrSort[$objFile->uuid] = (string) $objFile->name;
						break;

					case 'date':
						$sortType = SORT_NUMERIC;
						$arrSort[$objFile->uuid] = (int) $objFile->tstamp;
						break;

					default:
						// Unbekanntes Kriterium: Reihenfolge unveraendert lassen
						return false;
				}
			}

			asort($arrSort, $sortType);
			$this->arrUuids = array_keys($arrSort);
		}

		if ('DESC' === $strSortDirection)
		{
			$this->arrUuids = array_reverse($this->arrUuids);
		}

		return true;
	}

	/**
	 * Liest den Meta-Titel einer Datei in der aktuellen Sprache.
	 *
	 * @param FilesModel $objFile Der Dateidatensatz
	 *
	 * @return string Der Titel oder eine leere Zeichenkette, wenn zur aktuellen
	 *                Sprache keine Metadaten hinterlegt sind
	 */
	private function getMetaTitle(FilesModel $objFile): string
	{
		$arrMeta = StringUtil::deserialize($objFile->meta, true);
		$strLanguage = $GLOBALS['TL_LANGUAGE'] ?? 'de';

		if (isset($arrMeta[$strLanguage]['title']))
		{
			return (string) $arrMeta[$strLanguage]['title'];
		}

		return '';
	}

	/**
	 * Liefert die aufgeloeste und gegebenenfalls sortierte Dateiliste.
	 *
	 * @return array<int, string> Die UUIDs in binaerer Form
	 */
	public function getImageUuids(): array
	{
		return $this->arrUuids;
	}
}
