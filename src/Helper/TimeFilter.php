<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Helper;

use Contao\StringUtil;

/**
 * Rechnet die im Modul eingestellte Zeitspanne in Zeitstempel um.
 *
 * Im Backend steht dort etwas wie „von vor 10 Tagen bis vor 5 Tagen“. Diese
 * Klasse macht daraus zwei feste Zeitpunkte und beantwortet anschliessend die
 * Frage, ob ein Album mit seinem Start- und Enddatum in diese Spanne faellt.
 */
class TimeFilter
{
	/**
	 * Beginn der Zeitspanne als Unix-Zeitstempel.
	 *
	 * @var int|null
	 */
	private $intFilterStart;

	/**
	 * Ende der Zeitspanne als Unix-Zeitstempel.
	 *
	 * @var int|null
	 */
	private $intFilterEnd;

	/**
	 * @param mixed $varFilterStart Wert des Feldes `pa2TimeFilterStart`, also
	 *                              ein Feld mit `unit` und `value` — auch in
	 *                              serialisierter Form
	 * @param mixed $varFilterEnd   Wert des Feldes `pa2TimeFilterEnd`
	 */
	public function __construct($varFilterStart, $varFilterEnd)
	{
		$arrFilterStart = StringUtil::deserialize($varFilterStart, true);
		$arrFilterEnd = StringUtil::deserialize($varFilterEnd, true);

		if ($this->checkFilterVar($arrFilterStart) && $this->checkFilterVar($arrFilterEnd))
		{
			$this->intFilterStart = $this->getFilterTimestamp($arrFilterStart, false);
			$this->intFilterEnd = $this->getFilterTimestamp($arrFilterEnd, true);
		}
	}

	/**
	 * Prueft, ob eine Zeitangabe vollstaendig ist.
	 *
	 * @param mixed $var Die zu pruefende Angabe
	 *
	 * @return bool true, wenn es ein Feld mit `unit` und einem numerischen
	 *              `value` ist
	 */
	private function checkFilterVar($var): bool
	{
		return \is_array($var) && isset($var['unit'], $var['value']) && is_numeric($var['value']);
	}

	/**
	 * Rechnet eine Zeitangabe in einen Zeitstempel um.
	 *
	 * Gerechnet wird immer von heute rueckwaerts. Beim Ende der Spanne wird
	 * zusaetzlich eine Einheit aufgeschlagen, damit der genannte Tag, die
	 * Woche, der Monat oder das Jahr noch vollstaendig dazugehoert — sonst
	 * fiele „bis vor 0 Tagen“ auf Mitternacht heute und damit auf einen leeren
	 * Zeitraum.
	 *
	 * @param array<string, mixed> $arrData Feld mit `unit` und `value`
	 * @param bool                 $blnEnd  true fuer das Ende der Spanne
	 *
	 * @return int|null Der Zeitstempel oder null bei unbekannter Einheit
	 */
	private function getFilterTimestamp(array $arrData, bool $blnEnd): ?int
	{
		$intValue = (int) $arrData['value'];
		$intDay = (int) date('j');
		$intMonth = (int) date('n');
		$intYear = (int) date('Y');

		switch ($arrData['unit'])
		{
			case 'days':
				$intTs = mktime(0, 0, 0, $intMonth, $intDay + ($blnEnd ? 1 : 0) - $intValue, $intYear);
				break;

			case 'weeks':
				$intTs = mktime(0, 0, 0, $intMonth, $intDay + ($blnEnd ? 7 : 0) - ($intValue * 7) - ((int) date('N') - 1), $intYear);
				break;

			case 'months':
				$intTs = mktime(0, 0, 0, $intMonth + ($blnEnd ? 1 : 0) - $intValue, 1, $intYear);
				break;

			case 'years':
				$intTs = mktime(0, 0, 0, 1, 1, $intYear + ($blnEnd ? 1 : 0) - $intValue);
				break;

			default:
				return null;
		}

		return false === $intTs ? null : $intTs;
	}

	/**
	 * Entscheidet, ob ein Album aus der Ausgabe fliegt.
	 *
	 * Ein Album bleibt drin, sobald **eines** seiner beiden Daten in die
	 * Zeitspanne faellt. Ist gar kein Filter gesetzt, bleibt ebenfalls alles
	 * drin.
	 *
	 * @param mixed $dateStart Startdatum des Albums als Unix-Zeitstempel
	 * @param mixed $dateEnd   Enddatum des Albums als Unix-Zeitstempel
	 *
	 * @return bool true, wenn das Album **herausgefiltert** werden soll
	 */
	public function doFilter($dateStart, $dateEnd): bool
	{
		if (null === $this->intFilterStart || null === $this->intFilterEnd)
		{
			return false;
		}

		$intStart = (int) $dateStart;
		$intEnd = (int) $dateEnd;

		if ($this->intFilterStart <= $intStart && $intStart < $this->intFilterEnd)
		{
			return false;
		}

		if ($this->intFilterStart <= $intEnd && $intEnd < $this->intFilterEnd)
		{
			return false;
		}

		return true;
	}

	/**
	 * Liefert den Beginn der Zeitspanne.
	 *
	 * @return int|null Der Zeitstempel oder null, wenn kein Filter gesetzt ist
	 */
	public function getFilterStart(): ?int
	{
		return $this->intFilterStart;
	}

	/**
	 * Liefert das Ende der Zeitspanne.
	 *
	 * @return int|null Der Zeitstempel oder null, wenn kein Filter gesetzt ist
	 */
	public function getFilterEnd(): ?int
	{
		return $this->intFilterEnd;
	}
}
