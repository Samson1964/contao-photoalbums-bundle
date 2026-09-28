<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Helper;

/**
 * Entscheidet, was in einem Album ein Video ist, und liefert die Angaben dazu.
 *
 * Ein Album darf neben Bildern auch Videos enthalten. Die beiden werden
 * unterschiedlich behandelt: Ein Bild geht durch die Bildbearbeitung von Contao
 * und landet skaliert in der Kachel; ein Video bekommt eine einheitliche
 * Platzhalterkachel und wird beim Anklicken im mitgelieferten Überlagerer
 * abgespielt.
 *
 * Warum nicht die Lightbox des Themes? Sie bekommt die Verweise über das
 * Attribut `data-lightbox`, und die gängigen Lightboxen — colorbox in Contaos
 * Template `j_colorbox`, mediabox unter MooTools — bekommen ihre Einstellungen
 * dort **einmal für alle** Verweise. colorbox erkennt am Dateinamen nur
 * Bilder und würde ein Video zu laden versuchen. Ein Video trägt deshalb
 * bewusst **kein** `data-lightbox`, sondern `data-pa2-video`; darum kümmert
 * sich das eigene Skript.
 */
class Video
{
	/**
	 * Voreinstellung der Dateiendungen, die als Video gelten.
	 *
	 * Alle vier dürfen mit Contaos Voreinstellung für `uploadTypes` ohne
	 * weitere Einrichtung hochgeladen werden.
	 *
	 * @var string
	 */
	public const DEFAULT_EXTENSIONS = 'mp4,m4v,webm,ogv';

	/**
	 * Pfad der Platzhaltergrafik, relativ zum Webverzeichnis.
	 *
	 * @var string
	 */
	public const PLACEHOLDER = Assets::PATH.'images/video.svg';

	/**
	 * Liefert die Dateiendungen, die als Video gelten.
	 *
	 * Eine Installation kann die Liste in ihrer eigenen `config.php` ergänzen,
	 * indem sie `$GLOBALS['pa2']['videoExtensions']` überschreibt.
	 *
	 * @return array<int, string> Die Endungen, klein geschrieben
	 */
	public static function getExtensions(): array
	{
		$strList = (string) ($GLOBALS['pa2']['videoExtensions'] ?? self::DEFAULT_EXTENSIONS);

		return array_filter(array_map('strtolower', array_map('trim', explode(',', $strList))));
	}

	/**
	 * Prüft, ob eine Dateiendung zu einem Video gehört.
	 *
	 * @param string|null $strExtension Die Endung ohne Punkt
	 *
	 * @return bool true, wenn die Endung in der Videoliste steht
	 */
	public static function isVideoExtension(?string $strExtension): bool
	{
		if (null === $strExtension || '' === $strExtension)
		{
			return false;
		}

		return \in_array(strtolower($strExtension), self::getExtensions(), true);
	}

	/**
	 * Liefert das Kürzel des Medientyps für das `type`-Attribut.
	 *
	 * Ohne diese Angabe muss der Browser den Typ erraten; mit ihr entscheidet
	 * er sofort, ob er die Datei abspielen kann.
	 *
	 * @param string|null $strExtension Die Endung ohne Punkt
	 *
	 * @return string Der MIME-Typ oder eine leere Zeichenkette, wenn die
	 *                Endung nicht bekannt ist
	 */
	public static function getMimeType(?string $strExtension): string
	{
		switch (strtolower((string) $strExtension))
		{
			case 'mp4':
			case 'm4v':
				return 'video/mp4';

			case 'webm':
				return 'video/webm';

			case 'ogv':
				return 'video/ogg';

			default:
				return '';
		}
	}
}
