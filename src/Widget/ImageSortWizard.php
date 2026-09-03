<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Widget;

use Contao\Database;
use Contao\DataContainer;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\Validator;
use Contao\Widget;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Assets;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Thumbnail;
use Schachbulle\ContaoPhotoalbumsBundle\Sorter\FileSorter;

/**
 * Backend-Assistent zum Sortieren der Fotos eines Albums.
 *
 * Der Assistent zeigt alle Fotos, die im Feld `images` ausgewaehlt sind — auch
 * die aus ausgewaehlten Ordnern — als Daumennagelreihe und laesst sie mit der
 * Maus in die gewuenschte Reihenfolge ziehen. Gespeichert wird die Reihenfolge
 * als Feld von Datei-UUIDs.
 *
 * Die Klasse stammt urspruenglich aus der Erweiterung
 * `craffft/contao-imagesortwizard` und ist hier fest eingebaut. Zwei Dinge
 * wurden dabei ersetzt:
 *
 * 1. Das Umsortieren lief frueher ueber Adressparameter (`cmd_imageSort=up`)
 *    mit einem sofortigen Datenbankschreibvorgang je Klick. Jetzt wird nur noch
 *    im Browser umsortiert; gespeichert wird beim Absenden des Formulars.
 * 2. Das Ziehen mit der Maus benutzte MooTools (`Sortables`). Contao 5 liefert
 *    MooTools im Backend nicht mehr aus; das mitgelieferte Skript kommt deshalb
 *    ohne jede Bibliothek aus.
 */
class ImageSortWizard extends Widget
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
	 * @var string
	 */
	protected $strTemplate = 'be_widget';

	/**
	 * Prueft und normalisiert die abgeschickten Werte.
	 *
	 * Der Browser schickt die UUIDs in lesbarer Form zurueck; gespeichert wird
	 * die binaere Form, so wie sie auch im Feld `images` steht.
	 *
	 * Die Elternfassung wird bewusst **nicht** aufgerufen: `Widget::validator()`
	 * ruft sich bei einem Feld ueber `array_map` selbst fuer jeden Eintrag auf.
	 * Da die eigene Fassung dann mit einer einzelnen Zeichenkette ankaeme,
	 * entstuende bei jedem Speichern ein Feld aus leeren Feldern — die
	 * Reihenfolge waere weg. Die Pflichtfeldpruefung wird deshalb hier selbst
	 * erledigt.
	 *
	 * @param mixed $varInput Das Feld der UUIDs aus dem Formular
	 *
	 * @return array<int, string> Das Feld mit binaeren UUIDs; ungueltige
	 *                            Eintraege fallen heraus
	 */
	public function validator($varInput)
	{
		$arrUuids = array();

		foreach ((array) $varInput as $varValue)
		{
			if (!\is_string($varValue))
			{
				continue;
			}

			// Erst auf die binaere Form pruefen: Ein trim() koennte dort
			// Bytes abschneiden, die zufaellig wie Leerraum aussehen
			if (Validator::isBinaryUuid($varValue))
			{
				$arrUuids[] = $varValue;

				continue;
			}

			$strUuid = trim($varValue);

			if (Validator::isStringUuid($strUuid))
			{
				$arrUuids[] = StringUtil::uuidToBin($strUuid);
			}
		}

		if (empty($arrUuids) && $this->mandatory)
		{
			$this->addError(sprintf($GLOBALS['TL_LANG']['ERR']['mandatory'] ?? '%s', $this->strLabel));
		}

		return $arrUuids;
	}

	/**
	 * Erzeugt das Markup des Assistenten.
	 *
	 * @return string Die Liste der Daumennaegel mit versteckten Eingabefeldern;
	 *                ohne ausgewaehlte Fotos ein Hinweistext
	 */
	public function generate()
	{
		Assets::addSortWizardAssets();

		$arrUuids = $this->getOrderedUuids();

		if (empty($arrUuids))
		{
			return '<p class="tl_noopt">'.($GLOBALS['TL_LANG']['MSC']['noResult'] ?? '').'</p>';
		}

		$objFiles = FilesModel::findMultipleByUuids($arrUuids);

		if (null === $objFiles)
		{
			return '<p class="tl_noopt">'.($GLOBALS['TL_LANG']['MSC']['noResult'] ?? '').'</p>';
		}

		// Die Datensaetze nach UUID greifbar machen, damit die Ausgabe der
		// gespeicherten Reihenfolge folgt und nicht der Sortierung der Abfrage
		$arrFiles = array();

		while ($objFiles->next())
		{
			$arrFiles[StringUtil::binToUuid($objFiles->uuid)] = $objFiles->current();
		}

		$strReturn = '<div id="ctrl_'.$this->strId.'" class="tl_imagesortwizard" data-pa2-sortwizard="images">';
		$strReturn .= '<ul class="sortable">';

		foreach ($arrUuids as $uuid)
		{
			$strUuid = StringUtil::binToUuid($uuid);

			if (!isset($arrFiles[$strUuid]))
			{
				continue;
			}

			$objFile = $arrFiles[$strUuid];

			$strReturn .= '<li draggable="true" class="pa2-sortitem" title="'.StringUtil::specialchars((string) $objFile->name).'">';
			$strReturn .= $this->generateThumbnail((string) $objFile->path, (string) $objFile->name);
			$strReturn .= '<input type="hidden" name="'.$this->strName.'[]" value="'.StringUtil::specialchars($strUuid).'">';
			$strReturn .= '</li>';
		}

		$strReturn .= '</ul>';
		$strReturn .= '</div>';

		return $strReturn;
	}

	/**
	 * Bringt gespeicherte Reihenfolge und aktuelle Auswahl zur Deckung.
	 *
	 * Fotos, die aus dem Album entfernt wurden, fallen heraus; neu
	 * hinzugekommene werden hinten angehaengt. Nur so bleibt eine von Hand
	 * festgelegte Reihenfolge erhalten, wenn nachtraeglich Fotos dazukommen.
	 *
	 * @return array<int, string> Die Datei-UUIDs in binaerer Form
	 */
	private function getOrderedUuids(): array
	{
		$arrAvailable = $this->getSelectedUuids();

		if (empty($arrAvailable))
		{
			return array();
		}

		$arrStored = \is_array($this->varValue) ? $this->varValue : StringUtil::deserialize($this->varValue, true);

		$arrResult = array();

		foreach ($arrStored as $uuid)
		{
			if (\in_array($uuid, $arrAvailable))
			{
				$arrResult[] = $uuid;
			}
		}

		foreach ($arrAvailable as $uuid)
		{
			if (!\in_array($uuid, $arrResult))
			{
				$arrResult[] = $uuid;
			}
		}

		return $arrResult;
	}

	/**
	 * Liest die im Album ausgewaehlten Fotos aus der Datenbank.
	 *
	 * Gelesen wird das Feld, das in der DCA unter `eval.sortfiles` genannt ist
	 * — beim Fotoalbum also `images`. Ausgewaehlte Ordner werden dabei
	 * rekursiv aufgeloest.
	 *
	 * @return array<int, string> Die Datei-UUIDs in binaerer Form; leer, wenn
	 *                            der Datensatz noch gar nicht angelegt ist
	 */
	private function getSelectedUuids(): array
	{
		$strField = (string) $this->sortfiles;
		$intId = $this->getRecordId();

		if ('' === $strField || $intId < 1 || '' === (string) $this->strTable)
		{
			return array();
		}

		// Feldnamen gegen die DCA pruefen, damit nichts Fremdes in die Abfrage geraet
		if (!isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$strField]))
		{
			return array();
		}

		$objRecord = Database::getInstance()
			->prepare('SELECT '.$strField.' FROM '.$this->strTable.' WHERE id=?')
			->limit(1)
			->execute($intId);

		if ($objRecord->numRows < 1)
		{
			return array();
		}

		$objFileSorter = new FileSorter(StringUtil::deserialize($objRecord->$strField, true), $this->extensions);

		return $objFileSorter->getImageUuids();
	}

	/**
	 * Ermittelt die Nummer des gerade bearbeiteten Datensatzes.
	 *
	 * `$dc->id` liefert in beiden Contao-Fassungen die Datensatznummer und ist
	 * dem seit Contao 5 veralteten `activeRecord` vorzuziehen.
	 *
	 * @return int Die Nummer oder 0, wenn sie sich nicht ermitteln laesst
	 */
	private function getRecordId(): int
	{
		if ($this->dataContainer instanceof DataContainer && is_numeric($this->dataContainer->id))
		{
			return (int) $this->dataContainer->id;
		}

		if (is_numeric($this->currentRecord))
		{
			return (int) $this->currentRecord;
		}

		return 0;
	}

	/**
	 * Erzeugt den Daumennagel eines Fotos.
	 *
	 * @param string $strPath Projektrelativer Pfad der Bilddatei
	 * @param string $strName Dateiname, dient als Alternativtext
	 *
	 * @return string Das img-Element oder — wenn sich kein Bild erzeugen laesst
	 *                — der Dateiname als Text, damit sich der Eintrag trotzdem
	 *                anfassen und verschieben laesst
	 */
	private function generateThumbnail(string $strPath, string $strName): string
	{
		$strImage = Thumbnail::generate($strPath, $strName);

		if ('' !== $strImage)
		{
			return $strImage;
		}

		return '<span class="pa2-sortitem-name">'.StringUtil::specialchars($strName).'</span>';
	}
}
