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
use Contao\Controller;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\DataContainer;
use Contao\Image;
use Contao\Input;
use Contao\StringUtil;
use Contao\UserGroupModel;
use Contao\UserModel;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Runtime;

/**
 * Rueckrufe des Datenbereichs `tl_photoalbums2_archive`.
 *
 * Die Klasse erbt bewusst nicht von `Contao\Backend`; siehe die Begruendung
 * bei {@see AlbumListener}.
 */
class ArchiveListener
{
	/**
	 * Prueft, ob der angemeldete Benutzer den Vorgang ausfuehren darf.
	 *
	 * Nebenbei wird die Liste der sichtbaren Archive auf die erlaubten
	 * eingeschraenkt und das Anlegen neuer Archive gesperrt, wenn das Recht
	 * dazu fehlt.
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

		$GLOBALS['TL_DCA']['tl_photoalbums2_archive']['list']['sorting']['root'] = $arrRoot;

		if (!$objUser->hasAccess('create', 'photoalbums2p'))
		{
			$GLOBALS['TL_DCA']['tl_photoalbums2_archive']['config']['closed'] = true;
		}

		switch (Input::get('act'))
		{
			case 'create':
			case 'select':
			case '':
				break;

			case 'edit':
				// Ein gerade selbst angelegtes Archiv dem eigenen Profil zuschlagen
				if (!\in_array(Input::get('id'), $arrRoot))
				{
					$this->grantAccessToNewRecord($objUser, $arrRoot);
				}
				// Kein break: die Rechte werden unten geprueft

			case 'copy':
			case 'delete':
			case 'show':
				if (!\in_array(Input::get('id'), $arrRoot) || ('delete' === Input::get('act') && !$objUser->hasAccess('delete', 'photoalbums2p')))
				{
					$this->deny('Nicht genug Rechte, um "'.Input::get('act').'" auf dem Fotoalben-Archiv ID "'.Input::get('id').'" auszufuehren.');
				}
				break;

			case 'editAll':
			case 'deleteAll':
			case 'overrideAll':
				$this->restrictSelection($objUser, $arrRoot);
				break;

			default:
				$this->deny('Nicht genug Rechte, um "'.Input::get('act').'" auf den Fotoalben-Archiven auszufuehren.');
		}
	}

	/**
	 * Traegt ein frisch angelegtes Archiv in die Rechte des Benutzers ein.
	 *
	 * Ohne diesen Schritt koennte ein Benutzer mit dem Recht „anlegen“ ein
	 * Archiv erzeugen, es danach aber nicht mehr oeffnen.
	 *
	 * @param BackendUser            $objUser Der angemeldete Benutzer
	 * @param array<int, int|string> $arrRoot Die bisher erlaubten Archive,
	 *                                       wird bei Erfolg ergaenzt
	 *
	 * @return void
	 */
	private function grantAccessToNewRecord(BackendUser $objUser, array &$arrRoot): void
	{
		$objSession = Runtime::getSession();

		if (null === $objSession)
		{
			return;
		}

		$arrNew = $objSession->getBag('contao_backend')->get('new_records');

		if (!\is_array($arrNew['tl_photoalbums2_archive'] ?? null) || !\in_array(Input::get('id'), $arrNew['tl_photoalbums2_archive']))
		{
			return;
		}

		$arrGroups = StringUtil::deserialize($objUser->groups, true);

		// Rechte auf Benutzerebene
		if ('custom' === $objUser->inherit || empty($arrGroups[0]))
		{
			$objRecord = UserModel::findByPk($objUser->id);
		}
		// Rechte auf Gruppenebene
		elseif ($arrGroups[0] > 0)
		{
			$objRecord = UserGroupModel::findByPk($arrGroups[0]);
		}
		else
		{
			return;
		}

		if (null === $objRecord)
		{
			return;
		}

		$arrPermissions = StringUtil::deserialize($objRecord->photoalbums2p, true);

		if (!\in_array('create', $arrPermissions))
		{
			return;
		}

		$arrArchives = StringUtil::deserialize($objRecord->photoalbums2s, true);
		$arrArchives[] = Input::get('id');

		$objRecord->photoalbums2s = serialize(array_values(array_unique($arrArchives)));
		$objRecord->save();

		$arrRoot[] = Input::get('id');
		$objUser->photoalbums2s = $arrRoot;
	}

	/**
	 * Beschraenkt eine Sammelbearbeitung auf die erlaubten Archive.
	 *
	 * @param BackendUser            $objUser Der angemeldete Benutzer
	 * @param array<int, int|string> $arrRoot Die erlaubten Archive
	 *
	 * @return void
	 */
	private function restrictSelection(BackendUser $objUser, array $arrRoot): void
	{
		$objSession = Runtime::getSession();

		if (null === $objSession)
		{
			return;
		}

		$objBag = $objSession->getBag('contao_backend');
		$arrCurrent = $objBag->get('CURRENT');

		if (!\is_array($arrCurrent) || !isset($arrCurrent['IDS']) || !\is_array($arrCurrent['IDS']))
		{
			return;
		}

		if ('deleteAll' === Input::get('act') && !$objUser->hasAccess('delete', 'photoalbums2p'))
		{
			$arrCurrent['IDS'] = array();
		}
		else
		{
			$arrCurrent['IDS'] = array_intersect($arrCurrent['IDS'], $arrRoot);
		}

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
	 * Erzeugt die vorgemerkten Feeds neu.
	 *
	 * @return void
	 */
	public function generateFeed(): void
	{
		FeedListener::runScheduledUpdates();
	}

	/**
	 * Merkt das bearbeitete Archiv fuer eine Feed-Aktualisierung vor.
	 *
	 * @param DataContainer $dc Der Data Container
	 *
	 * @return void
	 */
	public function scheduleUpdate(DataContainer $dc): void
	{
		FeedListener::scheduleUpdate((int) $dc->id);
	}

	/**
	 * Zeigt den Loeschknopf nur, wenn das Recht dazu besteht.
	 *
	 * Die alte Rueckruf-Signatur mit dreizehn Einzelwerten funktioniert in
	 * beiden Contao-Fassungen. Wichtig ist, im gesperrten Fall eine **leere**
	 * Zeichenkette zurueckzugeben: `null` wuerde unter Contao 5 dazu fuehren,
	 * dass der Standardknopf doch erscheint.
	 *
	 * @param array<string, mixed> $row         Der Archivdatensatz
	 * @param string               $href        Der Zielparameter der Operation
	 * @param string               $label       Die Beschriftung
	 * @param string               $title       Der Titel des Verweises
	 * @param string               $icon        Das Symbol
	 * @param string|object        $attributes  Zusaetzliche Attribute
	 *
	 * @return string Das Markup des Knopfes oder eine leere Zeichenkette
	 */
	public function deleteArchive($row, $href, $label, $title, $icon, $attributes): string
	{
		$objUser = BackendUser::getInstance();

		if (!$objUser->isAdmin && !$objUser->hasAccess('delete', 'photoalbums2p'))
		{
			return '';
		}

		return '<a href="'.Controller::addToUrl($href.'&amp;id='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
	}
}
