<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer;

use Contao\System;
use Schachbulle\ContaoPhotoalbumsBundle\Feed\FeedGenerator;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Runtime;

/**
 * Merkt vor, welche Feeds nach einer Aenderung neu zu erzeugen sind.
 *
 * Das Erzeugen selbst geschieht nicht beim Speichern, sondern beim naechsten
 * Aufruf des Backend-Moduls. Das ist ein Kunstgriff aus dem Contao-Kern: Wer
 * zwanzig Alben hintereinander bearbeitet, soll nicht zwanzigmal auf das
 * Schreiben einer XML-Datei mit hunderten Eintraegen warten.
 */
class FeedListener
{
	/**
	 * Schluessel, unter dem die vorgemerkten Archive in der Sitzung stehen.
	 *
	 * @var string
	 */
	private const SESSION_KEY = 'pa2_feed_updater';

	/**
	 * Merkt ein Archiv fuer die naechste Feed-Aktualisierung vor.
	 *
	 * @param int $intArchiveId Datensatznummer des Archivs
	 *
	 * @return void Ohne Sitzung oder ohne gueltige Nummer geschieht nichts
	 */
	public static function scheduleUpdate(int $intArchiveId): void
	{
		if ($intArchiveId < 1)
		{
			return;
		}

		$objSession = Runtime::getSession();

		if (null === $objSession)
		{
			return;
		}

		$arrIds = $objSession->get(self::SESSION_KEY);
		$arrIds = \is_array($arrIds) ? $arrIds : array();
		$arrIds[] = $intArchiveId;

		$objSession->set(self::SESSION_KEY, array_values(array_unique($arrIds)));
	}

	/**
	 * Erzeugt die vorgemerkten Feeds und leert die Vormerkung.
	 *
	 * @return void Ohne Vormerkung geschieht nichts
	 */
	public static function runScheduledUpdates(): void
	{
		$objSession = Runtime::getSession();

		if (null === $objSession)
		{
			return;
		}

		$arrIds = $objSession->get(self::SESSION_KEY);

		if (!\is_array($arrIds) || empty($arrIds))
		{
			return;
		}

		$objGenerator = new FeedGenerator(System::getContainer()->get('contao.framework'));

		foreach ($arrIds as $intId)
		{
			$objGenerator->generateFeed((int) $intId);
		}

		$objSession->set(self::SESSION_KEY, null);
	}
}
