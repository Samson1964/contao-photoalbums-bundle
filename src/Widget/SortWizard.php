<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Widget;

use Contao\StringUtil;
use Contao\Widget;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Assets;

/**
 * Backend-Assistent zum Sortieren einer Auswahlliste.
 *
 * Gebraucht wird er im Frontend-Modul, um die Alben von Hand in eine eigene
 * Reihenfolge zu bringen. Gespeichert wird die **vollstaendige** Liste der
 * Albumnummern in der gewuenschten Reihenfolge; die Ausgabe im Frontend
 * beruecksichtigt sie nur, wenn als Sortierung „Eigene Sortierung“ gewaehlt ist.
 *
 * Die Klasse stammt urspruenglich aus der Erweiterung
 * `craffft/contao-sortwizard` und ist hier fest eingebaut. Wie beim
 * {@see ImageSortWizard} wurde das MooTools-Ziehen durch ein eigenes Skript
 * ersetzt und der Umweg ueber Adressparameter samt sofortigem
 * Datenbankschreibvorgang gestrichen.
 */
class SortWizard extends Widget
{
	/**
	 * Der Wert dieses Feldes wird beim Absenden uebernommen.
	 *
	 * @var bool
	 */
	protected $blnSubmitInput = true;

	/**
	 * Das umgebende Backend-Template.
	 *
	 * Die Beschriftung erzeugt der Assistent selbst, deshalb die Fassung ohne
	 * eigenes Label.
	 *
	 * @var string
	 */
	protected $strTemplate = 'be_widget_chk';

	/**
	 * Die zur Auswahl stehenden Eintraege.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected $arrOptions = array();

	/**
	 * Nimmt die Auswahlliste entgegen.
	 *
	 * @param string $strKey   Der Name der Eigenschaft
	 * @param mixed  $varValue Der Wert; bei `options` ein Feld von Eintraegen
	 *                         mit `value` und `label`
	 *
	 * @return void
	 */
	public function __set($strKey, $varValue)
	{
		if ('options' === $strKey)
		{
			$this->arrOptions = StringUtil::deserialize($varValue, true);

			return;
		}

		parent::__set($strKey, $varValue);
	}

	/**
	 * Erzeugt das Markup des Assistenten.
	 *
	 * @return string Die sortierbare Liste; ohne Eintraege ein Hinweistext
	 */
	public function generate()
	{
		Assets::addSortWizardAssets();

		$arrOptions = $this->getSortedOptions();
		$arrRows = array();

		foreach ($arrOptions as $arrOption)
		{
			$arrRows[] = sprintf(
				'<span draggable="true" class="pa2-sortitem"><input type="hidden" name="%s[]" value="%s"><span class="pa2-sortitem-label">%s</span></span>',
				$this->strName,
				StringUtil::specialchars((string) $arrOption['value']),
				StringUtil::specialchars((string) $arrOption['label'])
			);
		}

		if (empty($arrRows))
		{
			$arrRows[] = '<p class="tl_noopt">'.($GLOBALS['TL_LANG']['MSC']['noResult'] ?? '').'</p>';
		}

		return sprintf(
			'<div id="ctrl_%s" class="tl_sortwizard%s" data-pa2-sortwizard="options"><h3><label>%s</label>%s</h3><input type="hidden" name="%s" value=""><div class="sortable">%s</div></div>%s',
			$this->strId,
			'' !== (string) $this->strClass ? ' '.$this->strClass : '',
			$this->strLabel,
			$this->xlabel,
			$this->strName,
			implode('', $arrRows),
			$this->wizard
		);
	}

	/**
	 * Bringt die Auswahlliste in die gespeicherte Reihenfolge.
	 *
	 * Eintraege, die in der gespeicherten Reihenfolge vorkommen, stehen vorn
	 * und in genau dieser Folge; alles Neue haengt hinten an. Damit
	 * verschwindet ein neu angelegtes Album nicht aus der Liste, nur weil die
	 * Reihenfolge aelter ist als das Album.
	 *
	 * @return array<int, array<string, mixed>> Die sortierte Auswahlliste
	 */
	private function getSortedOptions(): array
	{
		$arrValue = \is_array($this->varValue) ? $this->varValue : StringUtil::deserialize($this->varValue, true);

		if (empty($arrValue))
		{
			return $this->arrOptions;
		}

		$arrSorted = array();
		$arrRest = $this->arrOptions;

		foreach ($this->arrOptions as $i => $arrOption)
		{
			$intPos = array_search($arrOption['value'], $arrValue);

			if (false !== $intPos)
			{
				$arrSorted[$intPos] = $arrOption;
				unset($arrRest[$i]);
			}
		}

		ksort($arrSorted);

		return array_merge($arrSorted, $arrRest);
	}
}
