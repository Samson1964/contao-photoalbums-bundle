<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\EventListener;

use Contao\PageModel;
use Contao\StringUtil;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\AlbumAlias;
use Schachbulle\ContaoPhotoalbumsBundle\Model\AlbumModel;
use Schachbulle\ContaoPhotoalbumsBundle\Routing\AlbumUrlResolver;

/**
 * Löst die Insert-Tags der Fotoalben auf.
 *
 * | Tag                          | Ergebnis                                   |
 * | ---------------------------- | ------------------------------------------ |
 * | `{{photoalbum_url::5}}`      | Adresse des Albums                         |
 * | `{{photoalbum::5}}`          | Vollständiger Verweis mit dem Titel        |
 * | `{{photoalbum_open::5}}`     | Nur das öffnende `<a>`                      |
 * | `{{photoalbum_title::5}}`    | Nur der Titel                              |
 *
 * Statt der Nummer darf auch der Alias stehen. Der Picker trägt aber
 * bewusst die **Nummer** ein: Ändert sich der Alias später, bleibt der Verweis
 * gültig. Mit dem Schalter `|absolute` (`{{photoalbum_url::5|absolute}}`)
 * entsteht eine Adresse mit Schema und Domain, wie sie etwa ein Newsletter
 * braucht.
 *
 * Registriert wird über den Hook `replaceInsertTags`. Das neue System der
 * Insert-Tag-Resolver gibt es erst ab Contao 5.2, der Hook dagegen wird auch
 * von Contao 5.7 noch für alle Tags aufgerufen, die kein Resolver kennt — so
 * funktioniert dieselbe Klasse in beiden Fassungen.
 *
 * Ein unveröffentlichtes oder unbekanntes Album ergibt eine leere
 * Zeichenkette, genau wie bei den Tags des Kerns. Dasselbe gilt, wenn keine
 * Seite das Album zeigt; wo das der Fall ist, sagt die Albenliste im Backend.
 */
class InsertTagsListener
{
	/**
	 * Die Tags, die diese Klasse beantwortet.
	 *
	 * @var array<int, string>
	 */
	private const TAGS = array('photoalbum', 'photoalbum_open', 'photoalbum_url', 'photoalbum_title');

	/**
	 * Bearbeitet einen einzelnen Insert-Tag.
	 *
	 * @param string            $strTag       Der Tag ohne Klammern, etwa
	 *                                        `photoalbum_url::5`
	 * @param bool              $blnUseCache  Vom Kern übergeben, hier ohne Belang
	 * @param mixed             $varCached    Vom Kern übergeben, hier ohne Belang
	 * @param array<int, string> $arrFlags    Die Schalter hinter `|`, etwa `absolute`
	 *
	 * @return string|false Das Ergebnis, oder false, wenn der Tag nicht zu
	 *                      diesem Bundle gehört — dann fragt Contao den
	 *                      nächsten Hook
	 */
	public function __invoke(string $strTag, bool $blnUseCache = true, $varCached = null, array $arrFlags = array())
	{
		$arrParts = explode('::', $strTag, 2);
		$strName = strtolower($arrParts[0]);

		if (!\in_array($strName, self::TAGS, true))
		{
			return false;
		}

		$strValue = trim((string) ($arrParts[1] ?? ''));

		if ('' === $strValue)
		{
			return '';
		}

		$objAlbums = AlbumModel::findPublishedByIdOrAlias($strValue);

		if (null !== $objAlbums && $objAlbums->count() > 0)
		{
			$objAlbum = $objAlbums->current();
		}
		else
		{
			// Ein von Hand geschriebener Tag mit einem alten Umlaut-Alias
			// (`{{photoalbum_url::dsam-düsseldorf-2023}}`) findet das Album
			// über dessen umgeschriebenen Alias
			$objAlbum = AlbumAlias::findRenamed($strValue);

			if (null === $objAlbum)
			{
				return '';
			}
		}
		$strTitle = StringUtil::specialchars(strip_tags((string) $objAlbum->title));

		if ('photoalbum_title' === $strName)
		{
			return $strTitle;
		}

		global $objPage;

		$objResolver = new AlbumUrlResolver();
		$strUrl = $objResolver->generate($objAlbum, \in_array('absolute', $arrFlags, true), $objPage instanceof PageModel ? $objPage : null);

		switch ($strName)
		{
			case 'photoalbum_url':
				return $strUrl;

			case 'photoalbum_open':
				return sprintf('<a href="%s" title="%s">', StringUtil::specialchars($strUrl), $strTitle);

			default:
				return sprintf('<a href="%s" title="%s">%s</a>', StringUtil::specialchars($strUrl), $strTitle, $strTitle);
		}
	}
}
