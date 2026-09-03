<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer;

use Contao\BackendUser;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\Database;
use Contao\DataContainer;
use Contao\FilesModel;
use Contao\Input;
use Contao\StringUtil;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Palette;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Runtime;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Thumbnail;
use Schachbulle\ContaoPhotoalbumsBundle\Model\AlbumModel;

/**
 * Rueckrufe des Datenbereichs `tl_photoalbums2_album`.
 *
 * Die Klasse erbt bewusst **nicht** von `Contao\Backend`: Sie braucht nichts
 * daraus, und ohne Elternklasse entfaellt die unter Contao 4.13 lauernde Falle
 * mit dem dort nur geschuetzten Konstruktor. Contao erzeugt sie ueber
 * `System::importStatic()`, was bei einem Klassennamen mit Namensraum und ohne
 * gleichnamigen Dienst schlicht `new` aufruft.
 */
class AlbumListener
{
	/**
	 * Prueft, ob der angemeldete Benutzer den Vorgang ausfuehren darf.
	 *
	 * Ein Benutzer darf nur mit Alben aus den Archiven arbeiten, die ihm oder
	 * seiner Gruppe unter „Erlaubte Archive“ zugewiesen sind.
	 *
	 * @param DataContainer $dc Der Data Container
	 *
	 * @return void
	 *
	 * @throws AccessDeniedException Wenn der Vorgang nicht erlaubt ist
	 */
	public function checkPermission(DataContainer $dc): void
	{
		$objUser = BackendUser::getInstance();

		if ($objUser->isAdmin)
		{
			return;
		}

		// Im Dateiverwaltungs-Modul wird die DCA nur mitgeladen
		if ('files' === Input::get('do'))
		{
			return;
		}

		$arrRoot = \is_array($objUser->photoalbums2s) && !empty($objUser->photoalbums2s) ? $objUser->photoalbums2s : array(0);

		$varId = \strlen((string) Input::get('id')) ? Input::get('id') : $dc->currentPid;

		switch (Input::get('act'))
		{
			case 'paste':
			case '':
				break;

			case 'create':
			case 'select':
				if (!\strlen((string) Input::get('id')) || !\in_array(Input::get('id'), $arrRoot))
				{
					$this->deny('Nicht genug Rechte, um im Fotoalben-Archiv ID "'.Input::get('pid').'" ein Album anzulegen.');
				}
				break;

			case 'cut':
			case 'copy':
				$varPid = Input::get('pid');

				// Beim Einfuegen "in ein Album" verweist pid auf das Geschwisteralbum
				if (1 == Input::get('mode'))
				{
					$objAlbum = AlbumModel::findByPk(Input::get('pid'));

					if (null === $objAlbum)
					{
						$this->deny('Ungueltige Fotoalbum-ID "'.Input::get('pid').'".');
					}

					$varPid = $objAlbum->pid;
				}

				if (!\in_array($varPid, $arrRoot))
				{
					$this->deny('Nicht genug Rechte, um das Fotoalbum ID "'.$varId.'" in das Archiv ID "'.Input::get('pid').'" zu verschieben.');
				}
				// Kein break: der Datensatz selbst wird unten geprueft

			case 'edit':
			case 'show':
			case 'delete':
			case 'toggle':
				$objAlbum = AlbumModel::findByPk($varId);

				if (null === $objAlbum)
				{
					$this->deny('Ungueltige Fotoalbum-ID "'.$varId.'".');
				}

				if (!\in_array($objAlbum->pid, $arrRoot))
				{
					$this->deny('Nicht genug Rechte, um das Fotoalbum ID "'.$varId.'" aus dem Archiv ID "'.$objAlbum->pid.'" zu bearbeiten.');
				}
				break;

			case 'editAll':
			case 'deleteAll':
			case 'overrideAll':
			case 'cutAll':
			case 'copyAll':
				if (!\in_array($varId, $arrRoot))
				{
					$this->deny('Nicht genug Rechte, um auf das Fotoalben-Archiv ID "'.$varId.'" zuzugreifen.');
				}

				$this->restrictSelection((int) $varId);
				break;

			default:
				$this->deny('Ungueltiger Befehl "'.Input::get('act').'".');
		}
	}

	/**
	 * Beschraenkt eine Sammelbearbeitung auf die Alben des erlaubten Archivs.
	 *
	 * @param int $intArchiveId Nummer des Archivs
	 *
	 * @return void
	 */
	private function restrictSelection(int $intArchiveId): void
	{
		$objSession = Runtime::getSession();

		if (null === $objSession)
		{
			return;
		}

		$objAlbums = AlbumModel::findByPid($intArchiveId);
		$arrAllowed = null !== $objAlbums ? $objAlbums->fetchEach('id') : array();

		$objBag = $objSession->getBag('contao_backend');
		$arrCurrent = $objBag->get('CURRENT');

		if (!\is_array($arrCurrent) || !isset($arrCurrent['IDS']) || !\is_array($arrCurrent['IDS']))
		{
			return;
		}

		$arrCurrent['IDS'] = array_intersect($arrCurrent['IDS'], $arrAllowed);
		$objBag->set('CURRENT', $arrCurrent);
	}

	/**
	 * Bricht den Vorgang ab und schreibt eine Meldung ins Protokoll.
	 *
	 * @param string $strMessage Die Begruendung
	 *
	 * @return void Die Methode kehrt nie zurueck
	 *
	 * @throws AccessDeniedException Immer
	 */
	private function deny(string $strMessage): void
	{
		Runtime::logError($strMessage, __METHOD__);

		throw new AccessDeniedException($strMessage);
	}

	/**
	 * Erzeugt die Zeile eines Albums in der Uebersicht des Backends.
	 *
	 * Neben dem Titel erscheint das Vorschaubild — oder, wenn keines gewaehlt
	 * ist, ein Hinweis darauf. Ueber die Systemeinstellung
	 * `pa2HidePreviewImageInBackend` laesst sich die Vorschau abschalten, was
	 * bei sehr grossen Archiven spuerbar Zeit spart.
	 *
	 * @param array<string, mixed> $arrRow Der Albumdatensatz
	 *
	 * @return string Das Markup der Zeile
	 */
	public function listAlbums(array $arrRow): string
	{
		$strKey = $arrRow['published'] ? 'published' : 'unpublished';
		$strContent = '';
		$blnShowPreview = empty($GLOBALS['TL_CONFIG']['pa2HidePreviewImageInBackend']);

		if ($blnShowPreview)
		{
			$strContent = $this->getPreviewMarkup($arrRow);
		}

		$strReturn = '<div class="cte_type '.$strKey.'"'.($blnShowPreview ? ' style="margin-bottom:0"' : '').'>';
		$strReturn .= $arrRow['title'];
		$strReturn .= '</div>';

		if ('' !== $strContent)
		{
			$strReturn .= $strContent."\n";
		}

		return $strReturn;
	}

	/**
	 * Baut die Vorschau eines Albums fuer die Backend-Uebersicht.
	 *
	 * @param array<string, mixed> $arrRow Der Albumdatensatz
	 *
	 * @return string Ein img-Element oder ein Hinweistext
	 */
	private function getPreviewMarkup(array $arrRow): string
	{
		if ('select_preview_image' === ($arrRow['previewImageType'] ?? ''))
		{
			$objFile = FilesModel::findByUuid($arrRow['previewImage']);

			if (null !== $objFile)
			{
				$strImage = Thumbnail::generate((string) $objFile->path, (string) $objFile->name);

				if ('' !== $strImage)
				{
					return $strImage;
				}
			}
		}

		$strType = (string) ($arrRow['previewImageType'] ?? '');

		if (isset($GLOBALS['TL_LANG']['PA2']['albumPreviewImageTypes'][$strType][0]))
		{
			return $GLOBALS['TL_LANG']['PA2']['albumPreviewImageTypes'][$strType][0];
		}

		return $GLOBALS['TL_LANG']['PA2']['albumPreviewImageTypes']['no_preview_image'][0] ?? '';
	}

	/**
	 * Erzeugt den Alias eines Albums, wenn keiner eingegeben wurde.
	 *
	 * @param mixed         $varValue Der eingegebene Alias
	 * @param DataContainer $dc       Der Data Container
	 *
	 * @return string Der gepruefte Alias
	 *
	 * @throws \Exception Wenn ein von Hand eingegebener Alias schon vergeben ist
	 */
	public function generateAlias($varValue, DataContainer $dc): string
	{
		$blnAutoAlias = false;

		if (!\strlen((string) $varValue))
		{
			$blnAutoAlias = true;

			$objRecord = Database::getInstance()
				->prepare('SELECT title FROM tl_photoalbums2_album WHERE id=?')
				->limit(1)
				->execute($dc->id);

			$varValue = StringUtil::standardize((string) $objRecord->title);
		}

		$objAlias = AlbumModel::findBy(
			array('tl_photoalbums2_album.id!=?', 'tl_photoalbums2_album.alias=?'),
			array($dc->id, $varValue)
		);

		if (null !== $objAlias && !$blnAutoAlias)
		{
			throw new \Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasExists'] ?? 'Der Alias "%s" ist bereits vergeben.', $varValue));
		}

		// Bei automatisch erzeugten Aliassen die Datensatznummer anhaengen
		if (null !== $objAlias && $blnAutoAlias)
		{
			$varValue .= '-'.$dc->id;
		}

		return (string) $varValue;
	}

	/**
	 * Ergaenzt fehlende Datumsangaben und haelt Start und Ende plausibel.
	 *
	 * **Hier steckt die Behebung des Fehlers mit Daten vor 1970.** Die
	 * Urfassung hat mit `$startdate < 1` geprueft und deshalb jeden negativen
	 * Zeitstempel als „nicht gesetzt“ behandelt: Ein eingegebenes Datum von
	 * 1968 wurde beim Speichern kommentarlos durch das heutige ersetzt.
	 * Geprueft wird jetzt auf eine **leere** Angabe.
	 *
	 * Der `onsubmit_callback` laeuft in beiden Contao-Fassungen nach dem
	 * Schreiben des Datensatzes; die Werte werden deshalb aus der Datenbank
	 * gelesen und dort auch wieder abgelegt.
	 *
	 * @param DataContainer $dc Der Data Container
	 *
	 * @return void
	 */
	public function adjustTime(DataContainer $dc): void
	{
		$intId = (int) $dc->id;

		if ($intId < 1)
		{
			return;
		}

		$objRecord = Database::getInstance()
			->prepare('SELECT startdate, enddate FROM tl_photoalbums2_album WHERE id=?')
			->limit(1)
			->execute($intId);

		if ($objRecord->numRows < 1)
		{
			return;
		}

		$strStart = (string) $objRecord->startdate;
		$strEnd = (string) $objRecord->enddate;

		// Ohne Startdatum den heutigen Tag nehmen
		$intStart = '' === $strStart ? mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y')) : (int) $strStart;

		// Ein leeres oder zu frueh liegendes Enddatum auf das Startdatum ziehen.
		// Der Wert 0 gilt in Altbestaenden als "kein Enddatum" und wird deshalb
		// wie eine leere Angabe behandelt.
		if ('' === $strEnd || 0 === (int) $strEnd || (int) $strEnd < $intStart)
		{
			$intEnd = $intStart;
		}
		else
		{
			$intEnd = (int) $strEnd;
		}

		if ((string) $intStart === $strStart && (string) $intEnd === $strEnd)
		{
			return;
		}

		Database::getInstance()
			->prepare('UPDATE tl_photoalbums2_album SET startdate=?, enddate=? WHERE id=?')
			->execute((string) $intStart, (string) $intEnd, $intId);
	}

	/**
	 * Erzeugt die vorgemerkten Feeds neu.
	 *
	 * Laeuft als `onload_callback`, also beim naechsten Aufruf des
	 * Backend-Moduls nach einer Aenderung. So bleibt das Speichern selbst
	 * schnell, auch wenn ein Archiv viele Alben hat.
	 *
	 * @return void
	 */
	public function generateFeed(): void
	{
		FeedListener::runScheduledUpdates();
	}

	/**
	 * Merkt das Archiv des bearbeiteten Albums fuer eine Feed-Aktualisierung vor.
	 *
	 * @param DataContainer $dc Der Data Container
	 *
	 * @return void
	 */
	public function scheduleUpdate(DataContainer $dc): void
	{
		if ('copy' === Input::get('act'))
		{
			return;
		}

		FeedListener::scheduleUpdate((int) $dc->currentPid);
	}

	/**
	 * Blendet Felder aus, die zur gewaehlten Einstellung nicht passen.
	 *
	 * Das Feld fuer das ausgewaehlte Vorschaubild erscheint nur bei
	 * „Vorschau Foto auswaehlen“, der Sortier-Assistent nur bei „Eigene
	 * Sortierung“. Beides laesst sich nicht ueber `subpalettes` abbilden, weil
	 * dort nur ein einziger Wert je Selektor moeglich waere.
	 *
	 * @param DataContainer $dc Der Data Container
	 *
	 * @return void
	 */
	public function generatePalette(DataContainer $dc): void
	{
		$objAlbum = AlbumModel::findByPk($dc->id);

		if (null === $objAlbum)
		{
			return;
		}

		if ('select_preview_image' !== $objAlbum->previewImageType)
		{
			Palette::removeField('tl_photoalbums2_album', 'default', 'previewImage');
		}

		if ('custom' !== $objAlbum->imageSortType)
		{
			Palette::removeField('tl_photoalbums2_album', 'default', 'imageSort');
		}
	}
}
