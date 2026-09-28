<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Routing;

use Contao\Database;
use Contao\PageModel;
use Contao\StringUtil;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Runtime;

/**
 * Beantwortet die Frage: Unter welcher Adresse ist ein Album zu sehen?
 *
 * Anders als bei Nachrichten oder Veranstaltungen gibt es bei den Fotoalben
 * keine feste „Weiterleitungsseite“ am Archiv. Wo ein Album erscheint, ergibt
 * sich aus der Einbindung: aus der Detailseite eines Moduls, aus der Seite, auf
 * der ein Leser-Modul steht, oder aus einem Inhaltselement, das genau dieses
 * Album zeigt. Diese Klasse sucht all diese Stellen zusammen und wählt nach
 * einer festen Rangfolge die beste.
 *
 * Rangfolge — die erste veröffentlichte Seite gewinnt:
 *
 * 1. Die ausdrücklich am Archiv eingetragene Seite (`modulePage`). Sie
 *    übersteuert alles Folgende und ist der Ausweg, wenn die Automatik einmal
 *    danebenliegt.
 * 2. Die Detailseite eines Moduls „Fotoalbum“ im Modus „auf getrennten Seiten“
 *    oder eines Moduls „Fotoalben Liste“. Hier hat die Redaktion selbst
 *    gesagt, wo die Fotos stehen.
 * 3. Die Seite, auf der ein Modul „Fotoalbum Leser“ eingebunden ist.
 * 4. Die Seite, auf der ein Modul „Fotoalbum“ im Modus „auf einer Seite“ steht.
 * 5. Die Seite eines Inhaltselements, das genau dieses Album zeigt. Der
 *    Verweis führt dann ohne Albumparameter auf die Seite — das Element zeigt
 *    sein Album ja fest.
 * 6. Die Seite eines Moduls im Modus „Nur Album-Ansicht mit Lightbox“; auch
 *    dieses zeigt die Fotos, sobald ein Album in der Adresse steht.
 *
 * Innerhalb dieser Stufen gehen Module vor, deren Archiv-Auswahl das Archiv des
 * Albums tatsächlich enthält. Die übrigen kommen nur zum Zug, wenn es gar
 * nichts Passenderes gibt: Die Foto-Ansicht zeigt zwar jedes veröffentlichte
 * Album unabhängig von der Archiv-Auswahl, eine „fremde“ Galerieseite ist als
 * Ziel aber die schlechtere Wahl.
 *
 * Bei mehrsprachigen Auftritten gewinnt bei Gleichstand die Seite aus demselben
 * Seitenbaum wie die gerade aufgerufene — ein Verweis auf der englischen Seite
 * führt dann auf die englische Galerie.
 *
 * Nicht berücksichtigt werden Module, die über das Seitenlayout oder über
 * `{{insert_module::…}}` eingebunden sind: Sie hängen an keiner bestimmten
 * Seite, und eine davon zu raten wäre schlimmer als gar keine Antwort. Für
 * diese Fälle ist die Einstellung am Archiv da.
 */
class AlbumUrlResolver
{
	/**
	 * Stufen der Rangfolge; kleiner ist besser.
	 */
	public const SOURCE_ARCHIVE = 'archive';
	public const SOURCE_DETAIL_PAGE = 'detailPage';
	public const SOURCE_READER = 'reader';
	public const SOURCE_ONE_PAGE = 'onePage';
	public const SOURCE_CONTENT_ELEMENT = 'contentElement';
	public const SOURCE_ALBUM_VIEW = 'albumView';

	/**
	 * Rang je Quelle.
	 *
	 * @var array<string, int>
	 */
	private const LEVELS = array(
		self::SOURCE_ARCHIVE => 1,
		self::SOURCE_DETAIL_PAGE => 2,
		self::SOURCE_READER => 3,
		self::SOURCE_ONE_PAGE => 4,
		self::SOURCE_CONTENT_ELEMENT => 5,
		self::SOURCE_ALBUM_VIEW => 6,
	);

	/**
	 * Zwischenspeicher der Modul-Fundstellen für die Dauer einer Anfrage.
	 *
	 * Die Module ändern sich während einer Anfrage nicht; ohne diesen Speicher
	 * liefe jede Albumzeile einer Backend-Liste und jeder Insert-Tag auf einer
	 * Seite dieselben Abfragen erneut.
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private static $arrModuleCandidates;

	/**
	 * Zwischenspeicher: Album-ID => Seiten mit einem Inhaltselement dafür.
	 *
	 * @var array<int, array<int, int>>|null
	 */
	private static $arrElementPages;

	/**
	 * Zwischenspeicher der geprüften Seiten; `false` heißt nicht veröffentlicht.
	 *
	 * @var array<int, PageModel|false>
	 */
	private static $arrPages = array();

	/**
	 * Sucht die Seite, unter der ein Album zu sehen ist.
	 *
	 * @param object        $objAlbum    Der Albumdatensatz; gebraucht werden
	 *                                   `id` und `pid`
	 * @param PageModel|null $objCurrent Die gerade aufgerufene Seite, um bei
	 *                                   Gleichstand im selben Seitenbaum zu
	 *                                   bleiben; null im Backend
	 *
	 * @return array{page: PageModel, withParameter: bool, source: string, module: string}|null
	 *         Die Zielseite samt der Angabe, ob das Album als Parameter
	 *         angehängt werden muss, und woher die Antwort stammt; null, wenn
	 *         keine veröffentlichte Seite das Album zeigt
	 */
	public function findTarget($objAlbum, ?PageModel $objCurrent = null): ?array
	{
		$intAlbumId = (int) ($objAlbum->id ?? 0);
		$intArchiveId = (int) ($objAlbum->pid ?? 0);

		if ($intAlbumId < 1)
		{
			return null;
		}

		$arrCandidates = array();

		// 1. Ausdrückliche Vorgabe am Archiv
		$objArchive = Database::getInstance()
			->prepare('SELECT modulePage FROM tl_photoalbums2_archive WHERE id=?')
			->limit(1)
			->execute($intArchiveId);

		if ($objArchive->numRows > 0 && (int) $objArchive->modulePage > 0)
		{
			$arrCandidates[] = $this->candidate((int) $objArchive->modulePage, self::SOURCE_ARCHIVE, true, true);
		}

		// 2.–4. und 6. Module
		foreach ($this->getModuleCandidates() as $arrModule)
		{
			$blnMatch = empty($arrModule['archives']) ? false : \in_array($intArchiveId, $arrModule['archives'], true);

			foreach ($arrModule['pages'] as $intPageId)
			{
				$arrCandidates[] = $this->candidate($intPageId, $arrModule['source'], $blnMatch, true, $arrModule['name']);
			}
		}

		// 5. Inhaltselemente mit genau diesem Album
		foreach ($this->getElementPages()[$intAlbumId] ?? array() as $intPageId)
		{
			$arrCandidates[] = $this->candidate($intPageId, self::SOURCE_CONTENT_ELEMENT, true, false);
		}

		$intCurrentRoot = null !== $objCurrent ? (int) $objCurrent->rootId : 0;

		foreach ($arrCandidates as $i => $arrCandidate)
		{
			$objPage = $this->getPublishedPage($arrCandidate['pageId']);

			if (null === $objPage)
			{
				unset($arrCandidates[$i]);

				continue;
			}

			$arrCandidates[$i]['page'] = $objPage;
			$arrCandidates[$i]['sameRoot'] = $intCurrentRoot > 0 && (int) $objPage->rootId === $intCurrentRoot;
		}

		if (empty($arrCandidates))
		{
			return null;
		}

		$arrCandidates = self::sortCandidates($arrCandidates);
		$arrBest = reset($arrCandidates);

		return array(
			'page' => $arrBest['page'],
			'withParameter' => $arrBest['withParameter'],
			'source' => $arrBest['source'],
			'module' => $arrBest['module'],
		);
	}

	/**
	 * Erzeugt die Adresse eines Albums.
	 *
	 * @param object         $objAlbum   Der Albumdatensatz mit `id`, `pid` und `alias`
	 * @param bool           $blnAbsolute true für eine Adresse samt Schema und
	 *                                   Domain, etwa für Newsletter
	 * @param PageModel|null $objCurrent Die gerade aufgerufene Seite, siehe
	 *                                   {@see self::findTarget()}
	 *
	 * @return string Die Adresse; leer, wenn keine Seite das Album zeigt
	 */
	public function generate($objAlbum, bool $blnAbsolute = false, ?PageModel $objCurrent = null): string
	{
		$arrTarget = $this->findTarget($objAlbum, $objCurrent);

		if (null === $arrTarget)
		{
			return '';
		}

		$strParams = null;

		if ($arrTarget['withParameter'])
		{
			$strAlias = '' !== (string) ($objAlbum->alias ?? '') ? (string) $objAlbum->alias : (string) $objAlbum->id;
			$strParams = Runtime::useAutoItem() ? '/'.$strAlias : '/album/'.$strAlias;
		}

		/** @var PageModel $objPage */
		$objPage = $arrTarget['page'];

		return $blnAbsolute ? $objPage->getAbsoluteUrl($strParams) : $objPage->getFrontendUrl($strParams);
	}

	/**
	 * Baut einen Kandidaten mit seiner Wertung.
	 *
	 * Die Wertung setzt die Übereinstimmung mit dem Archiv vor die Stufe: Ein
	 * passendes Modul der Stufe 4 schlägt ein unpassendes der Stufe 2.
	 *
	 * @param int    $intPageId  Die Seitennummer
	 * @param string $strSource  Eine der Konstanten SOURCE_*
	 * @param bool   $blnMatch   true, wenn die Quelle das Archiv des Albums führt
	 * @param bool   $blnParam   true, wenn das Album in die Adresse muss
	 * @param string $strModule  Name des Moduls für die Anzeige im Backend
	 *
	 * @return array<string, mixed> Der Kandidat
	 */
	private function candidate(int $intPageId, string $strSource, bool $blnMatch, bool $blnParam, string $strModule = ''): array
	{
		return array(
			'pageId' => $intPageId,
			'source' => $strSource,
			'withParameter' => $blnParam,
			'module' => $strModule,
			'score' => self::score($strSource, $blnMatch),
		);
	}

	/**
	 * Berechnet die Wertung einer Fundstelle; kleiner ist besser.
	 *
	 * Die Übereinstimmung mit dem Archiv zählt hundert, die Stufe zehn je
	 * Rang. Damit schlägt jede passende Fundstelle jede unpassende, und erst
	 * innerhalb dieser beiden Gruppen entscheidet die Stufe.
	 *
	 * Öffentlich und ohne Datenbank, damit der Prüfstand die Rangfolge
	 * festschreiben kann.
	 *
	 * @param string $strSource Eine der Konstanten SOURCE_*
	 * @param bool   $blnMatch  true, wenn die Fundstelle das Archiv des Albums führt
	 *
	 * @return int Die Wertung
	 */
	public static function score(string $strSource, bool $blnMatch): int
	{
		return ($blnMatch ? 0 : 100) + (self::LEVELS[$strSource] ?? 9) * 10;
	}

	/**
	 * Bringt die Fundstellen in die Reihenfolge, in der sie gewählt werden.
	 *
	 * Nach der Wertung entscheidet der Seitenbaum: Bei Gleichstand gewinnt die
	 * Seite aus demselben Seitenbaum wie die aufgerufene. Zuletzt die kleinere
	 * Seitennummer — nicht weil sie besser wäre, sondern damit dieselbe
	 * Datenlage immer dieselbe Antwort ergibt.
	 *
	 * @param array<int, array<string, mixed>> $arrCandidates Fundstellen mit
	 *                                                        `score`, `sameRoot`
	 *                                                        und `pageId`
	 *
	 * @return array<int, array<string, mixed>> Dieselben, beste zuerst
	 */
	public static function sortCandidates(array $arrCandidates): array
	{
		usort(
			$arrCandidates,
			static function (array $a, array $b): int
			{
				return array($a['score'], empty($a['sameRoot']) ? 1 : 0, $a['pageId'])
					<=> array($b['score'], empty($b['sameRoot']) ? 1 : 0, $b['pageId']);
			}
		);

		return $arrCandidates;
	}

	/**
	 * Sammelt alle Module des Bundles samt der Seiten, auf die sie verweisen.
	 *
	 * Welche Seite zählt, hängt vom Modul ab: Bei einem Modul mit Detailseite
	 * ist es die Detailseite — die Übersicht selbst zeigt keine Fotos. Bei
	 * allen anderen ist es die Seite, auf der das Modul eingebunden ist.
	 *
	 * @return array<int, array<string, mixed>> Je Modul: `source`, `archives`,
	 *                                          `pages` und `name`
	 */
	private function getModuleCandidates(): array
	{
		if (null !== self::$arrModuleCandidates)
		{
			return self::$arrModuleCandidates;
		}

		$objDb = Database::getInstance();
		$objModules = $objDb->execute("SELECT id, name, type, pa2Mode, pa2DetailPage, pa2Archives FROM tl_module WHERE type IN ('photoalbums2', 'photoalbums2list', 'photoalbums2view')");

		$arrResult = array();

		while ($objModules->next())
		{
			$strMode = (string) $objModules->pa2Mode;
			$intDetail = (int) $objModules->pa2DetailPage;

			if ('photoalbums2list' === $objModules->type || ('photoalbums2' === $objModules->type && 'pa2_with_detail_page' === $strMode))
			{
				// Ohne Detailseite zeigt eine Liste nirgends Fotos
				if ($intDetail < 1)
				{
					continue;
				}

				$strSource = self::SOURCE_DETAIL_PAGE;
				$arrPages = array($intDetail);
			}
			else
			{
				if ('photoalbums2view' === $objModules->type)
				{
					$strSource = self::SOURCE_READER;
				}
				elseif ('pa2_only_album_view' === $strMode)
				{
					$strSource = self::SOURCE_ALBUM_VIEW;
				}
				else
				{
					$strSource = self::SOURCE_ONE_PAGE;
				}

				$arrPages = $this->findPagesWithModule((int) $objModules->id);
			}

			if (empty($arrPages))
			{
				continue;
			}

			$arrResult[] = array(
				'source' => $strSource,
				'archives' => array_map('intval', StringUtil::deserialize($objModules->pa2Archives, true)),
				'pages' => $arrPages,
				'name' => (string) $objModules->name,
			);
		}

		return self::$arrModuleCandidates = $arrResult;
	}

	/**
	 * Findet die Seiten, auf denen ein Modul über ein Inhaltselement steht.
	 *
	 * Gesucht wird nur in Artikeln; ein Element „Modul“ in einer Nachricht oder
	 * einem Event wäre ungewöhnlich und hätte keine eigene Seite. Versteckte
	 * Elemente und unveröffentlichte Artikel zählen nicht — eine Seite, auf der
	 * das Modul gar nicht erscheint, ist kein Ziel.
	 *
	 * `invisible!='1'` statt `invisible=''`: Contao 4.13 speichert ein
	 * sichtbares Element als leere Zeichenkette, Contao 5 als Zahl `0`. Die
	 * Abfrage auf die leere Zeichenkette träfe in Contao 5 nur über die
	 * Typumwandlung von MySQL — und im strengen SQL-Modus gar nicht.
	 *
	 * @param int $intModuleId Die Modulnummer
	 *
	 * @return array<int, int> Die Seitennummern
	 */
	private function findPagesWithModule(int $intModuleId): array
	{
		$intTime = time();

		$objResult = Database::getInstance()
			->prepare(
				"SELECT DISTINCT a.pid AS page FROM tl_content c INNER JOIN tl_article a ON a.id=c.pid
				 WHERE c.type='module' AND c.module=? AND (c.ptable='tl_article' OR c.ptable='')
				   AND c.invisible!='1' AND (c.start='' OR c.start<=?) AND (c.stop='' OR c.stop>?)
				   AND a.published='1' AND (a.start='' OR a.start<=?) AND (a.stop='' OR a.stop>?)"
			)
			->execute($intModuleId, $intTime, $intTime, $intTime, $intTime);

		return array_map('intval', $objResult->fetchEach('page'));
	}

	/**
	 * Liest auf einen Schlag alle Inhaltselemente „Fotoalbum“ ein.
	 *
	 * Eine Abfrage für alle Alben statt einer je Album: In der Backend-Liste
	 * eines Archivs stehen schnell fünfzig Alben untereinander.
	 *
	 * @return array<int, array<int, int>> Album-ID => Seitennummern
	 */
	private function getElementPages(): array
	{
		if (null !== self::$arrElementPages)
		{
			return self::$arrElementPages;
		}

		$intTime = time();

		$objResult = Database::getInstance()
			->prepare(
				"SELECT c.pa2Album AS album, a.pid AS page FROM tl_content c INNER JOIN tl_article a ON a.id=c.pid
				 WHERE c.type='photoalbums2' AND (c.ptable='tl_article' OR c.ptable='')
				   AND c.invisible!='1' AND (c.start='' OR c.start<=?) AND (c.stop='' OR c.stop>?)
				   AND a.published='1' AND (a.start='' OR a.start<=?) AND (a.stop='' OR a.stop>?)"
			)
			->execute($intTime, $intTime, $intTime, $intTime);

		$arrResult = array();

		while ($objResult->next())
		{
			$arrResult[(int) $objResult->album][] = (int) $objResult->page;
		}

		return self::$arrElementPages = $arrResult;
	}

	/**
	 * Liefert eine Seite, sofern sie veröffentlicht ist.
	 *
	 * @param int $intPageId Die Seitennummer
	 *
	 * @return PageModel|null Die Seite mit geladenen Details (für `rootId`)
	 *                        oder null, wenn es sie nicht gibt oder sie nicht
	 *                        veröffentlicht ist
	 */
	private function getPublishedPage(int $intPageId): ?PageModel
	{
		if (!isset(self::$arrPages[$intPageId]))
		{
			$objPage = $intPageId > 0 ? PageModel::findPublishedById($intPageId) : null;

			if (null !== $objPage)
			{
				$objPage->loadDetails();
			}

			self::$arrPages[$intPageId] = $objPage ?? false;
		}

		return false === self::$arrPages[$intPageId] ? null : self::$arrPages[$intPageId];
	}

	/**
	 * Leert die Zwischenspeicher.
	 *
	 * Gebraucht nur von den Prüfwerkzeugen, die in einem Lauf den
	 * Datenbestand verändern und danach erneut fragen.
	 *
	 * @return void
	 */
	public static function reset(): void
	{
		self::$arrModuleCandidates = null;
		self::$arrElementPages = null;
		self::$arrPages = array();
	}
}
