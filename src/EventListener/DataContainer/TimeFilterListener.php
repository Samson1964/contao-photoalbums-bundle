<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer;

use Contao\Database;
use Contao\DataContainer;
use Contao\StringUtil;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\TimeFilter;

/**
 * Haelt die beiden Werte des Zeitfilters plausibel.
 *
 * Negative Zahlen werden auf 0 gesetzt, und ein Ende, das vor dem Anfang
 * laege, wird auf den Anfang gezogen — sonst waere die Zeitspanne leer und im
 * Frontend erschiene gar kein Album.
 *
 * Der Rueckruf haengt als `onsubmit_callback` an `tl_module` und `tl_content`.
 * Die Urfassung hat in beiden Faellen nach `tl_module` geschrieben; hier wird
 * die Tabelle aus dem Data Container genommen.
 */
class TimeFilterListener
{
	/**
	 * Prueft und berichtigt die Werte des Zeitfilters.
	 *
	 * Der `onsubmit_callback` laeuft in beiden Contao-Fassungen **nach** dem
	 * Schreiben des Datensatzes; die Werte werden deshalb aus der Datenbank
	 * gelesen und dort auch wieder abgelegt.
	 *
	 * @param DataContainer $dc Der Data Container des Moduls oder Elements
	 *
	 * @return void Ohne Datensatznummer oder bei abgeschaltetem Zeitfilter
	 *              geschieht nichts
	 */
	public function onSubmit(DataContainer $dc): void
	{
		$intId = (int) $dc->id;
		$strTable = (string) $dc->table;

		if ($intId < 1 || !\in_array($strTable, array('tl_module', 'tl_content'), true))
		{
			return;
		}

		$objRecord = Database::getInstance()
			->prepare('SELECT pa2TimeFilter, pa2TimeFilterStart, pa2TimeFilterEnd FROM '.$strTable.' WHERE id=?')
			->limit(1)
			->execute($intId);

		if ($objRecord->numRows < 1 || !$objRecord->pa2TimeFilter)
		{
			return;
		}

		$arrStart = StringUtil::deserialize($objRecord->pa2TimeFilterStart, true);
		$arrEnd = StringUtil::deserialize($objRecord->pa2TimeFilterEnd, true);

		$arrStart['value'] = $this->normalizeValue($arrStart['value'] ?? '');
		$arrEnd['value'] = $this->normalizeValue($arrEnd['value'] ?? '');

		$objTimeFilter = new TimeFilter($arrStart, $arrEnd);

		// Ein Ende vor dem Anfang ergaebe eine leere Zeitspanne
		if (null !== $objTimeFilter->getFilterStart() && null !== $objTimeFilter->getFilterEnd() && $objTimeFilter->getFilterStart() > $objTimeFilter->getFilterEnd())
		{
			$arrEnd = $arrStart;
		}

		Database::getInstance()
			->prepare('UPDATE '.$strTable.' SET pa2TimeFilterStart=?, pa2TimeFilterEnd=? WHERE id=?')
			->execute(serialize($arrStart), serialize($arrEnd), $intId);
	}

	/**
	 * Macht aus einer Eingabe eine gueltige Anzahl.
	 *
	 * @param mixed $varValue Der eingegebene Wert
	 *
	 * @return string Die Anzahl als Zeichenkette; alles Leere oder Negative
	 *                wird zu "0"
	 */
	private function normalizeValue($varValue): string
	{
		if (!is_numeric($varValue) || $varValue < 0)
		{
			return '0';
		}

		return (string) (int) $varValue;
	}
}
