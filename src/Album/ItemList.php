<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Album;

/**
 * Gemeinsamer Unterbau von {@see Archive} und {@see Album}.
 *
 * Beide Klassen bekommen eine Liste von Datensatznummern und die Daten des
 * aufrufenden Moduls oder Inhaltselements. Aus den Moduldaten stammen
 * Einstellungen wie der Zeitfilter oder die gewuenschte Albensortierung; sie
 * werden hier ueber die magischen Zugriffsmethoden erreichbar gemacht, damit
 * die abgeleiteten Klassen einfach `$this->pa2TimeFilter` schreiben koennen.
 *
 * Die Urfassung erbte an dieser Stelle von `Contao\Controller`, benutzte davon
 * aber nichts. Die Elternklasse ist deshalb entfallen — das spart unter Contao
 * 5 gleich mehrere Deprecations.
 */
abstract class ItemList
{
	/**
	 * Die Datensatznummern, mit denen gearbeitet wird.
	 *
	 * @var array<int, int|string>
	 */
	private $arrItems = array();

	/**
	 * Die Daten des aufrufenden Moduls oder Inhaltselements.
	 *
	 * @var array<string, mixed>
	 */
	private $arrData = array();

	/**
	 * @param mixed                $varValue Eine einzelne Datensatznummer oder
	 *                                       ein Feld von Nummern; alles andere
	 *                                       ergibt eine leere Liste
	 * @param array<string, mixed> $arrData  Die Daten des aufrufenden Moduls
	 */
	public function __construct($varValue, $arrData)
	{
		if (is_numeric($varValue))
		{
			$this->arrItems = array($varValue);
		}
		elseif (\is_array($varValue))
		{
			$this->arrItems = $varValue;
		}

		if (\is_array($arrData))
		{
			$this->arrData = $arrData;
		}

		$this->sortOut();
	}

	/**
	 * Setzt die Nummernliste oder einen Wert aus den Moduldaten.
	 *
	 * @param string $strKey   Der Schluessel `items` meint die Nummernliste,
	 *                         jeder andere Schluessel die Moduldaten
	 * @param mixed  $varValue Der neue Wert
	 *
	 * @return void
	 */
	public function __set($strKey, $varValue)
	{
		if ('items' === $strKey)
		{
			$this->arrItems = $varValue;

			return;
		}

		$this->arrData[$strKey] = $varValue;
	}

	/**
	 * Liest die Nummernliste oder einen Wert aus den Moduldaten.
	 *
	 * @param string $strKey Der Schluessel `items` meint die Nummernliste,
	 *                       jeder andere Schluessel die Moduldaten
	 *
	 * @return mixed Der Wert oder null, wenn der Schluessel unbekannt ist
	 */
	public function __get($strKey)
	{
		if ('items' === $strKey)
		{
			return $this->arrItems;
		}

		return $this->arrData[$strKey] ?? null;
	}

	/**
	 * Prueft, ob ein Schluessel belegt ist.
	 *
	 * @param string $strKey Der zu pruefende Schluessel
	 *
	 * @return bool true, wenn der Schluessel einen Wert hat
	 */
	public function __isset($strKey)
	{
		if ('items' === $strKey)
		{
			return !empty($this->arrItems);
		}

		return isset($this->arrData[$strKey]);
	}

	/**
	 * Liefert die Daten des aufrufenden Moduls.
	 *
	 * @return array<string, mixed> Die Moduldaten, wie sie uebergeben wurden
	 */
	public function getData(): array
	{
		return $this->arrData;
	}

	/**
	 * Entfernt aus der Nummernliste alles, worauf der Besucher keinen Zugriff hat.
	 *
	 * Wird bereits im Konstruktor aufgerufen, damit jede weitere Auswertung mit
	 * einer bereits bereinigten Liste arbeitet.
	 *
	 * @return void
	 */
	abstract protected function sortOut(): void;
}
