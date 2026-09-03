<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Parser;

use Contao\Config;
use Contao\Date;
use Contao\FilesModel;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;
use Contao\UserModel;
use Schachbulle\ContaoPhotoalbumsBundle\Album\Album;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\EmptyTemplate;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Runtime;
use Schachbulle\ContaoPhotoalbumsBundle\Model\ArchiveModel;

/**
 * Gemeinsamer Unterbau der beiden Ansichten.
 *
 * Ein Parser bekommt das Template des Moduls oder Inhaltselements, reichert es
 * an und gibt es fertig zurueck. Die Aufteilung in `generate()` (Werte aus den
 * Moduleinstellungen uebernehmen) und `compile()` (Daten holen und Teil-
 * Templates bauen) stammt aus der Urfassung und ist beibehalten worden, damit
 * eigene Ableitungen weiter funktionieren.
 *
 * Die frueher hier benutzte Elternklasse `Contao\Frontend` ist entfallen. Ihre
 * Methoden `parseDate()`, `prepareMetaDescription()` und `generateFrontendUrl()`
 * gibt es unter Contao 5 nicht mehr; sie sind hier durch eigene, in beiden
 * Fassungen funktionierende Wege ersetzt.
 */
abstract class ViewParser
{
	/**
	 * Das Template, das am Ende ausgegeben wird.
	 *
	 * @var Template
	 */
	protected $Template;

	/**
	 * @param Template $objTemplate Das Template des Moduls oder Inhaltselements
	 */
	public function __construct($objTemplate)
	{
		if (!$objTemplate instanceof Template)
		{
			return;
		}

		$this->Template = $objTemplate;

		$this->generate();
		$this->compile();
	}

	/**
	 * Uebernimmt die Moduleinstellungen in die Arbeitsvariablen des Templates.
	 *
	 * Die Basisfassung tut nichts; die Ableitungen fuellen hier Werte wie
	 * `intItemsPerPage` oder `strSubtemplate`.
	 *
	 * @return void
	 */
	protected function generate(): void
	{
	}

	/**
	 * Holt die Daten und baut die Teil-Templates.
	 *
	 * @return void
	 */
	abstract protected function compile(): void;

	/**
	 * Ersetzt das Template durch die Meldung „nichts gefunden“.
	 *
	 * Die Gestaltungsangaben des Moduls (CSS-Klasse, ID, Ueberschrift) werden
	 * mituebernommen, damit die Meldung an derselben Stelle und im selben
	 * Rahmen erscheint wie sonst die Alben.
	 *
	 * @param string            $strMessage Der Meldungstext
	 * @param array<int, mixed> $arrItems   Bereits gefundene Eintraege; sind es
	 *                                      welche, bleibt das Template stehen
	 *
	 * @return void
	 */
	protected function setEmptyTemplate(string $strMessage, array $arrItems = array()): void
	{
		$objEmpty = new EmptyTemplate($strMessage, $arrItems);
		$objTemplate = $objEmpty->run();

		if (null === $objTemplate)
		{
			return;
		}

		$objTemplate->class = $this->Template->class ?? '';
		$objTemplate->cssID = $this->Template->cssID ?? '';
		$objTemplate->style = $this->Template->style ?? '';
		$objTemplate->headline = $this->Template->headline ?? '';
		$objTemplate->hl = $this->Template->hl ?? 'h1';

		$this->Template = $objTemplate;
	}

	/**
	 * Bereitet Text aus dem Rich-Text-Editor fuer die Ausgabe auf.
	 *
	 * Der frueher zusaetzliche Aufruf von `StringUtil::toHtml5()` ist entfallen:
	 * Die Methode gibt es unter Contao 5 nicht mehr, und sie hat ohnehin nur
	 * XHTML-Reste aus Contao-3-Zeiten aufgeraeumt.
	 *
	 * @param mixed $varText Der Text aus dem Editor
	 *
	 * @return string Der Text mit verschleierten E-Mail-Adressen
	 */
	public function cleanRteOutput($varText): string
	{
		if (!\is_string($varText) || '' === $varText)
		{
			return '';
		}

		return StringUtil::encodeEmail($varText);
	}

	/**
	 * Haengt das Kommentarformular an das Template.
	 *
	 * Kommentare setzen das Paket `contao/comments-bundle` voraus. Fehlt es,
	 * bleibt `allowComments` auf false und das Template gibt gar nichts aus —
	 * ein fehlendes Zusatzpaket darf die Fotoalben nicht lahmlegen.
	 *
	 * @param object $objAlbum Der Albumdatensatz
	 *
	 * @return void
	 */
	public function addComments($objAlbum): void
	{
		if ($objAlbum->noComments || !class_exists('Contao\Comments'))
		{
			$this->Template->allowComments = false;

			return;
		}

		$objArchive = ArchiveModel::findByPk($objAlbum->pid);

		if (null === $objArchive || !$objArchive->allowComments)
		{
			$this->Template->allowComments = false;

			return;
		}

		$this->Template->allowComments = true;

		// Die Kommentar-Ueberschrift eine Stufe unter die Modul-Ueberschrift setzen
		$intHl = min((int) str_replace('h', '', (string) ($this->Template->hl ?? 'h2')), 5);
		$this->Template->hlc = 'h'.($intHl + 1);

		$arrNotifies = array();

		if ('notify_author' !== $objArchive->notify && !empty($GLOBALS['TL_ADMIN_EMAIL']))
		{
			$arrNotifies[] = $GLOBALS['TL_ADMIN_EMAIL'];
		}

		if ('notify_admin' !== $objArchive->notify)
		{
			$objAuthor = UserModel::findByPk($objAlbum->author);

			if (null !== $objAuthor)
			{
				$arrNotifies[] = $objAuthor->email;
			}
		}

		$objConfig = new \stdClass();
		$objConfig->perPage = $objArchive->perPage;
		$objConfig->order = $objArchive->sortOrder;
		$objConfig->template = 'com_default';
		$objConfig->requireLogin = $objArchive->requireLogin;
		$objConfig->disableCaptcha = $objArchive->disableCaptcha;
		$objConfig->bbcode = $objArchive->bbcode;
		$objConfig->moderate = $objArchive->moderate;

		$strClass = 'Contao\Comments';
		$objComments = new $strClass();
		$objComments->addCommentsToTemplate($this->Template, $objConfig, 'tl_photoalbums2_album', $objAlbum->id, $arrNotifies);
	}

	/**
	 * Schreibt das Aufnahmedatum ins Template.
	 *
	 * **Hier steckt die Behebung des Fehlers mit Daten vor 1970.** Die
	 * Urfassung hat mit `$intStartdate > 0` geprueft und damit jeden negativen
	 * Zeitstempel verworfen — eine Olympiade von 1968 hatte im Frontend gar
	 * kein Datum. Geprueft wird jetzt auf „leer“ statt auf „groesser null“.
	 *
	 * Der Wert 0 gilt weiterhin als „kein Datum“: Er steht in Altbestaenden
	 * fuer ein nicht gesetztes Enddatum, und der 1. Januar 1970 ist als
	 * Aufnahmedatum eines Fotoalbums nicht zu erwarten.
	 *
	 * @param object $objTemplate   Das Ziel-Template
	 * @param mixed  $varStartdate  Startdatum als Unix-Zeitstempel
	 * @param mixed  $varEnddate    Enddatum als Unix-Zeitstempel
	 *
	 * @return object Dasselbe Template mit gesetzter Eigenschaft `date`
	 */
	protected function addDateToTemplate($objTemplate, $varStartdate, $varEnddate)
	{
		if (!\is_object($objTemplate))
		{
			return $objTemplate;
		}

		global $objPage;

		$strFormat = (string) ((null !== $objPage && '' !== (string) $objPage->dateFormat) ? $objPage->dateFormat : Config::get('dateFormat'));

		// Ohne Seitenobjekt und ohne Systemeinstellung bleibt der Contao-Standard
		if ('' === $strFormat)
		{
			$strFormat = 'd.m.Y';
		}

		$strStartdate = $this->parseAlbumDate($strFormat, $varStartdate);
		$strEnddate = $this->parseAlbumDate($strFormat, $varEnddate);

		if ('' === $strStartdate)
		{
			$objTemplate->date = $strEnddate;
		}
		elseif ('' === $strEnddate || $strStartdate === $strEnddate)
		{
			$objTemplate->date = $strStartdate;
		}
		else
		{
			$objTemplate->date = $strStartdate.' - '.$strEnddate;
		}

		return $objTemplate;
	}

	/**
	 * Formatiert einen einzelnen Zeitstempel eines Albums.
	 *
	 * @param string $strFormat Das Datumsformat der Seite
	 * @param mixed  $varTstamp Der Zeitstempel; leer oder 0 gilt als „nicht gesetzt“
	 *
	 * @return string Das formatierte Datum oder eine leere Zeichenkette
	 */
	private function parseAlbumDate(string $strFormat, $varTstamp): string
	{
		if (null === $varTstamp || '' === (string) $varTstamp || !is_numeric($varTstamp))
		{
			return '';
		}

		$intTstamp = (int) $varTstamp;

		if (0 === $intTstamp)
		{
			return '';
		}

		return Date::parse($strFormat, $intTstamp);
	}

	/**
	 * Baut aus den Meta-Feldern die Liste fuer die Ausgabe.
	 *
	 * Welche Felder erscheinen, steht im Modul (`pa2AlbumsMetaFields` bzw.
	 * `pa2ImagesMetaFields`). Je nach Einstellung wird die Beschriftung
	 * mitgegeben („Fotograf: Max Mustermann“) oder weggelassen.
	 *
	 * @param object $objTemplate Das Ziel-Template
	 *
	 * @return object Dasselbe Template, gegebenenfalls mit `metaFields`
	 */
	protected function addMetaFieldsToTemplate($objTemplate)
	{
		if (!\is_object($objTemplate))
		{
			return $objTemplate;
		}

		$arrMetaFields = StringUtil::deserialize($objTemplate->arrMetaFields, true);

		if (empty($arrMetaFields))
		{
			return $objTemplate;
		}

		$arrOutput = array();

		foreach ($arrMetaFields as $strField)
		{
			$varFieldValue = $objTemplate->$strField ?? '';

			if ('' === (string) $varFieldValue)
			{
				continue;
			}

			$varLabel = $objTemplate->showMetaDescriptions
				? ($GLOBALS['TL_LANG']['PA2']['pa2MetaFieldDescription'][$strField] ?? '%s')
				: ($GLOBALS['TL_LANG']['PA2']['pa2MetaFieldWithoutDescription'][$strField] ?? '');

			// Einige Beschriftungen haben eine Einzahl- und eine Mehrzahlform
			if (\is_array($varLabel))
			{
				$varLabel = ('1' == $varFieldValue || \count($varLabel) < 2) ? $varLabel[0] : $varLabel[1];
			}

			// Ohne Platzhalter wuerde der Wert selbst verlorengehen
			if (false === strpos((string) $varLabel, '%s'))
			{
				$varLabel = '%s';
			}

			$arrOutput[] = array(
				'key'   => $strField,
				'value' => sprintf((string) $varLabel, $varFieldValue),
			);
		}

		if (!empty($arrOutput))
		{
			$objTemplate->metaFields = $arrOutput;
		}

		return $objTemplate;
	}

	/**
	 * Setzt Positionsklassen und die Spaltenbreite eines Eintrags.
	 *
	 * Daraus entstehen im Markup die Klassen `first`, `last`, `even`, `odd`,
	 * `first_page`, `last_page`, `first_all`, `last_all` und `itemNumber_N`
	 * sowie die Umbrueche der Zeilen (`rowStart`/`rowEnd`).
	 *
	 * @param object $objTemplate Das Ziel-Template
	 * @param int    $i           Laufende Nummer des Eintrags auf dieser Seite,
	 *                            beginnend bei 0
	 *
	 * @return object Dasselbe Template
	 */
	protected function addSpecificClassesToTemplate($objTemplate, int $i)
	{
		if (!\is_object($objTemplate) || !is_numeric($objTemplate->totalItems) || !is_numeric($objTemplate->intItemsPerRow))
		{
			return $objTemplate;
		}

		$arrClasses = array();
		$arrStyles = array();

		$intTotalItems = (int) $objTemplate->totalItems;
		$intItemsPerRow = max((int) $objTemplate->intItemsPerRow, 1);
		$intItemsPerPage = (int) $objTemplate->intItemsPerPage;

		$objTemplate->rowStart = false;
		$objTemplate->rowEnd = false;

		// Ohne Seitenumbruch bilden alle Eintraege eine einzige Seite;
		// das verhindert zugleich eine Division durch null
		if ($intItemsPerPage < 1)
		{
			$intItemsPerPage = max($intTotalItems, 1);
		}

		$intItemNumberInRow = $i % $intItemsPerRow;
		$intItemNumberPerPage = $i % $intItemsPerPage;

		$intMaxPage = (int) ceil($intTotalItems / $intItemsPerPage);

		$intPage = (int) Input::get('page');
		$intPage = $intPage > 0 ? $intPage : 1;
		$intPage = ($intMaxPage > 0 && $intPage > $intMaxPage) ? $intMaxPage : $intPage;

		$intItemNumber = $i + 1 + (($intPage - 1) * $intItemsPerPage);

		$intFirstItemInPageNumber = (($intPage - 1) * $intItemsPerPage) + 1;
		$intFirstItemInPageNumber = min($intFirstItemInPageNumber, $intTotalItems);

		$intLastItemInPageNumber = min($intPage * $intItemsPerPage, $intTotalItems);

		$arrStyles[] = 'width: '.(100 / $intItemsPerRow).'%;';

		// Hoch- oder Querformat aus der eingestellten Bildgroesse ableiten
		$arrImageSize = StringUtil::deserialize($objTemplate->size, true);
		$intWidth = (int) ($arrImageSize[0] ?? 0);
		$intHeight = (int) ($arrImageSize[1] ?? 0);

		if ($intWidth > 0 && $intHeight > 0)
		{
			if ($intWidth > $intHeight)
			{
				$arrClasses[] = 'landscape';
			}
			elseif ($intWidth < $intHeight)
			{
				$arrClasses[] = 'portrait';
			}
			else
			{
				$arrClasses[] = 'square';
			}
		}

		if (0 === $intItemNumberInRow)
		{
			$arrClasses[] = 'first';
			$objTemplate->rowStart = true;
		}

		if (($intItemNumberInRow + 1) === $intItemsPerRow || ($intItemNumberPerPage + 1) === $intItemsPerPage || $intTotalItems === ($i + 1) || $intItemNumber === $intTotalItems)
		{
			$arrClasses[] = 'last';
			$objTemplate->rowEnd = true;
		}

		if ($intItemNumber === $intFirstItemInPageNumber)
		{
			$arrClasses[] = 'first_page';
		}

		if ($intItemNumber === $intLastItemInPageNumber)
		{
			$arrClasses[] = 'last_page';
		}

		if (1 === $intItemNumber)
		{
			$arrClasses[] = 'first_all';
		}

		if ($intItemNumber === $intTotalItems)
		{
			$arrClasses[] = 'last_all';
		}

		$arrClasses[] = 'itemNumber_'.$intItemNumber;
		$arrClasses[] = (0 === $i % 2) ? 'even' : 'odd';

		// Zeilen abwechselnd kennzeichnen
		if (0 === $intItemNumberInRow)
		{
			if (!isset($GLOBALS['pa2']['pa2RowEvenOdd']))
			{
				$GLOBALS['pa2']['pa2RowEvenOdd'] = 0;
			}

			$objTemplate->rowClass = (0 === $GLOBALS['pa2']['pa2RowEvenOdd'] % 2) ? 'even' : 'odd';

			++$GLOBALS['pa2']['pa2RowEvenOdd'];
		}

		$strClass = (string) ($objTemplate->class ?? '');
		$objTemplate->class = ('' === $strClass ? '' : $strClass.' ').implode(' ', $arrClasses);

		$strStyle = (string) ($objTemplate->style ?? '');
		$objTemplate->style = ('' === $strStyle ? '' : $strStyle.' ').implode(' ', $arrStyles);

		$objTemplate->itemNumber = $intItemNumber;
		$objTemplate->firstItemInPageNumber = $intFirstItemInPageNumber;
		$objTemplate->lastItemInPageNumber = $intLastItemInPageNumber;

		return $objTemplate;
	}

	/**
	 * Setzt den Verweis auf die Foto-Ansicht eines Albums.
	 *
	 * Ziel ist entweder die im Modul eingestellte Detailseite oder — wenn keine
	 * eingestellt ist — die aktuelle Seite. Der Ersatz fuer das unter Contao 5
	 * entfallene `Controller::generateFrontendUrl()` ist
	 * `PageModel::getFrontendUrl()`; es gibt beide Fassungen.
	 *
	 * @param object $objTemplate Das Ziel-Template
	 * @param mixed  $varAlbum    Albumnummer oder Albumdatensatz
	 *
	 * @return object Dasselbe Template mit gesetzter Eigenschaft `href`
	 */
	protected function addLinkToTemplate($objTemplate, $varAlbum)
	{
		global $objPage;

		if (!\is_object($objTemplate))
		{
			return $objTemplate;
		}

		if (is_numeric($varAlbum))
		{
			$objAlbumList = new Album($varAlbum, $objTemplate->getData());
			$objAlbums = $objAlbumList->getAlbums();

			if (null === $objAlbums)
			{
				return $objTemplate;
			}

			$varAlbum = $objAlbums->current();
		}

		if (!\is_object($varAlbum))
		{
			return $objTemplate;
		}

		$intPageId = (null !== $objPage) ? (int) $objPage->id : 0;

		if (!empty($objTemplate->intDetailPage) && is_numeric($objTemplate->intDetailPage))
		{
			$intPageId = (int) $objTemplate->intDetailPage;
		}

		$objTargetPage = PageModel::findWithDetails($intPageId);

		if (null === $objTargetPage)
		{
			return $objTemplate;
		}

		$strAlias = '' !== (string) $varAlbum->alias ? $varAlbum->alias : $varAlbum->id;
		$strParams = Runtime::useAutoItem() ? '/'.$strAlias : '/album/'.$strAlias;

		$objTemplate->href = $objTargetPage->getFrontendUrl($strParams);

		return $objTemplate;
	}

	/**
	 * Erzeugt eine im Seitenaufbau eindeutige Kennung.
	 *
	 * Gebraucht wird sie, um die Lightbox-Gruppen mehrerer Alben auf derselben
	 * Seite auseinanderzuhalten.
	 *
	 * @return string Zwoelf Zeichen aus einer Pruefsumme
	 */
	protected function generateIndividualId(): string
	{
		if (!isset($GLOBALS['pa2']['individualId']) || !\is_array($GLOBALS['pa2']['individualId']))
		{
			$GLOBALS['pa2']['individualId'] = array('count' => 0, 'id' => array());
		}

		++$GLOBALS['pa2']['individualId']['count'];

		$strId = substr(md5('pa2_'.$GLOBALS['pa2']['individualId']['count']), 1, 12);

		if (\in_array($strId, $GLOBALS['pa2']['individualId']['id'] ?? array(), true))
		{
			return $this->generateIndividualId();
		}

		$GLOBALS['pa2']['individualId']['id'][] = $strId;

		return $strId;
	}

	/**
	 * Liest den Titel eines Fotos aus den Metadaten.
	 *
	 * @param FilesModel|null $objImage Der Dateidatensatz des Fotos
	 *
	 * @return string Der Meta-Titel in der Seitensprache, ersatzweise der
	 *                englische und zuletzt der Dateiname
	 */
	protected function getImageTitle($objImage): string
	{
		if (!\is_object($objImage))
		{
			return '';
		}

		$arrMeta = StringUtil::deserialize($objImage->meta, true);
		$strLanguage = $GLOBALS['TL_LANGUAGE'] ?? 'de';

		if (!empty($arrMeta[$strLanguage]['title']))
		{
			return (string) $arrMeta[$strLanguage]['title'];
		}

		if (!empty($arrMeta['en']['title']))
		{
			return (string) $arrMeta['en']['title'];
		}

		return (string) $objImage->name;
	}

	/**
	 * Kuerzt einen Text auf die Laenge einer Meta-Beschreibung.
	 *
	 * Ersatz fuer `Frontend::prepareMetaDescription()`, das es unter Contao 5
	 * nicht mehr gibt.
	 *
	 * @param mixed $varText Der Ausgangstext, ueblicherweise die
	 *                       Albumbeschreibung aus dem Editor
	 *
	 * @return string Der auf 320 Zeichen gekuerzte Text ohne Markup
	 */
	protected function prepareDescription($varText): string
	{
		if (!\is_string($varText) || '' === $varText)
		{
			return '';
		}

		$strText = Runtime::replaceInsertTags($varText);
		$strText = strip_tags($strText);
		$strText = str_replace("\n", ' ', $strText);

		return trim(StringUtil::substr($strText, 320));
	}

	/**
	 * Liefert das fertig aufbereitete Template.
	 *
	 * @return Template Das Template, das der Aufrufer ausgeben soll
	 */
	public function getViewParserTemplate()
	{
		return $this->Template;
	}
}
