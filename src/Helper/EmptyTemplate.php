<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Helper;

use Contao\FrontendTemplate;

/**
 * Erzeugt das Ersatz-Template fuer den Fall, dass nichts auszugeben ist.
 *
 * Statt einer leeren Flaeche erscheint dann ein Satz wie „Es sind keine
 * Fotoalben vorhanden!“.
 *
 * Die Urfassung hat an dieser Stelle zusaetzlich per `header()` einen
 * 404-Status gesetzt. Das ist ersatzlos entfallen: Unter Symfony wird die
 * Statuszeile beim Senden der Response ohnehin ueberschrieben, der Aufruf war
 * also schon unter Contao 4 wirkungslos. Ein echter 404 waere hier auch
 * unpassend — die Seite selbst gibt es ja, nur das Modul hat nichts zu zeigen.
 */
class EmptyTemplate
{
	/**
	 * Die auszugebende Meldung.
	 *
	 * @var string
	 */
	private $strMessage;

	/**
	 * Die bereits gefundenen Eintraege.
	 *
	 * @var array<int, mixed>
	 */
	private $arrItems;

	/**
	 * @param string $strMessage Der Meldungstext, ueblicherweise aus
	 *                           $GLOBALS['TL_LANG']['MSC']
	 * @param mixed  $arrItems   Die bereits gefundenen Eintraege; sind es
	 *                           welche, wird gar kein Ersatz-Template erzeugt
	 */
	public function __construct(string $strMessage, $arrItems = array())
	{
		$this->strMessage = $strMessage;
		$this->arrItems = \is_array($arrItems) ? $arrItems : array();
	}

	/**
	 * Erzeugt das Ersatz-Template.
	 *
	 * @return FrontendTemplate|null Das Template `pa2_empty` mit gesetzter
	 *                               Meldung oder null, wenn doch Eintraege
	 *                               vorliegen und die Ausgabe normal
	 *                               weiterlaufen soll
	 */
	public function run(): ?FrontendTemplate
	{
		if (!empty($this->arrItems))
		{
			return null;
		}

		$objTemplate = new FrontendTemplate('pa2_empty');
		$objTemplate->empty = $this->strMessage;

		return $objTemplate;
	}
}
