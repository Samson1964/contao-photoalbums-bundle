<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Helper;

use Ausi\SlugGenerator\SlugGenerator;
use Contao\Database;
use Schachbulle\ContaoPhotoalbumsBundle\Model\AlbumModel;

/**
 * Bildet den Alias eines Albums — ausschließlich aus a–z, 0–9 und Bindestrich.
 *
 * **Warum nicht einfach `StringUtil::standardize()`?** Unter Contao 3 hat diese
 * Methode Umlaute umschrieben, unter Contao 4 und 5 lässt sie sie stehen. So
 * sind seit dem Umstieg auf Contao 4 Aliase wie `dsam-düsseldorf-2023`
 * entstanden (im Abzug vom 03.09.2026: 4 von 618), während die übrigen
 * durchgehend der Form `laenderkampf-oesterreich-…` folgen. In
 * einer Adresse erscheint ein Umlaut als `%C3%BC`, und beim Kopieren oder in
 * manchen Mailprogrammen geht er ganz verloren.
 *
 * Umgeschrieben wird mit dem Slug-Generator, den Contao selbst mitbringt
 * (`ausi/slug-generator`), und zwar mit deutschem Gebietsschema. Das trifft die
 * alte Konvention genau: `ä` → `ae`, `ß` → `ss`, und auch Akzente und
 * osteuropäische Zeichen (`Čačak` → `cacak`) werden sauber zu ASCII. Contaos
 * Dienst `contao.slug` wird bewusst nicht benutzt: Er richtet sich nach den
 * erlaubten Zeichen der Seite, und die lassen Umlaute ab Werk zu — genau das
 * Verhalten, das hier weg soll.
 *
 * Beginnt ein Alias mit einer Ziffer, bekommt er wie bisher `id-` vorangestellt
 * (`id-33-deutsche-loesemeisterschaft-2009`). Ein rein numerischer Alias wäre
 * sonst von einer Albumnummer nicht zu unterscheiden.
 */
class AlbumAlias
{
	/**
	 * Der Generator, einmal gebaut und wiederverwendet.
	 *
	 * @var SlugGenerator|null
	 */
	private static $objGenerator;

	/**
	 * Macht aus einem Titel oder einem alten Alias einen gültigen Alias.
	 *
	 * @param string $strText Der Titel oder ein vorhandener Alias
	 *
	 * @return string Der Alias; leer, wenn vom Text nichts Verwertbares bleibt
	 *                (etwa bei einem Titel nur aus Satzzeichen)
	 */
	public static function generate(string $strText): string
	{
		if (null === self::$objGenerator)
		{
			self::$objGenerator = new SlugGenerator(array('validChars' => '0-9a-z', 'locale' => 'de', 'delimiter' => '-'));
		}

		$strAlias = self::$objGenerator->generate($strText);

		if ('' !== $strAlias && ctype_digit($strAlias[0]))
		{
			$strAlias = 'id-'.$strAlias;
		}

		return $strAlias;
	}

	/**
	 * Sagt, ob ein Alias umgeschrieben werden muss.
	 *
	 * Geprüft wird nur auf Zeichen außerhalb von ASCII. Aliase, die zwar ASCII
	 * sind, aber anders aussehen, als der Generator sie heute bilden würde
	 * (etwa `bundesliga-in-muelheimruhr-2011` statt `…-muelheim-ruhr-…`),
	 * bleiben unangetastet: Ihre Adressen funktionieren, und jede Änderung
	 * bräche Verweise von außen.
	 *
	 * @param string $strAlias Der Alias
	 *
	 * @return bool true, wenn er Umlaute oder andere Sonderzeichen enthält
	 */
	public static function needsRewrite(string $strAlias): bool
	{
		return (bool) preg_match('/[^\x00-\x7F]/', $strAlias);
	}

	/**
	 * Sucht ein Album unter der umgeschriebenen Fassung eines alten Alias.
	 *
	 * Wer noch eine alte Adresse wie `…/dsam-düsseldorf-2023` aufruft, findet
	 * das Album sonst nicht mehr. Das Umschreiben ist eindeutig, also lässt
	 * sich der neue Alias aus dem alten ausrechnen.
	 *
	 * **Vorrang hat ein Alias der Form „neuer Alias + Bindestrich + eigene
	 * Nummer“.** Hießen zwei Alben `köln-2020` und `koeln-2020`, bekam das
	 * erste beim Umstellen `koeln-2020-<Nummer>`. Die alte Adresse
	 * `…/köln-2020` gehört zu genau diesem Album — und nicht zu dem, das schon
	 * immer `koeln-2020` hieß und unter einem Umlaut-Alias nie erreichbar war.
	 * Ohne diesen Vorrang führte die Weiterleitung zum falschen Album. Gibt es
	 * mehrere solcher Alben, gewinnt das älteste; es ist das, das schon vor der
	 * Umstellung bestand.
	 *
	 * @param string $strAlias Der Alias aus der Adresse
	 *
	 * @return AlbumModel|null Das veröffentlichte Album oder null, wenn der
	 *                         Alias nichts umzuschreiben hatte oder auch der
	 *                         neue nicht vorkommt
	 */
	public static function findRenamed(string $strAlias): ?AlbumModel
	{
		if ('' === $strAlias || is_numeric($strAlias) || !self::needsRewrite($strAlias))
		{
			return null;
		}

		$strNew = self::generate($strAlias);

		if ('' === $strNew || $strNew === $strAlias)
		{
			return null;
		}

		// Erst das Album, das bei der Umstellung die eigene Nummer angehängt bekam
		$objSuffixed = Database::getInstance()
			->prepare("SELECT id FROM tl_photoalbums2_album WHERE alias=CONCAT(?, '-', id) ORDER BY id")
			->execute($strNew);

		while ($objSuffixed->next())
		{
			$objAlbums = AlbumModel::findPublishedByIdOrAlias((int) $objSuffixed->id);

			if (null !== $objAlbums && $objAlbums->count() > 0)
			{
				return $objAlbums->current();
			}
		}

		$objAlbums = AlbumModel::findPublishedByIdOrAlias($strNew);

		return (null !== $objAlbums && $objAlbums->count() > 0) ? $objAlbums->current() : null;
	}
}
