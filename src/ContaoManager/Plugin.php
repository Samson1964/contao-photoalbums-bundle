<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Schachbulle\ContaoPhotoalbumsBundle\ContaoPhotoalbumsBundle;

/**
 * Meldet das Bundle beim Contao Manager an.
 *
 * Ohne diese Klasse taucht das Bundle nicht im Kernel auf, weil Contao die
 * Bundle-Liste aus den Plugins aller installierten Pakete zusammensetzt.
 */
class Plugin implements BundlePluginInterface
{
	/**
	 * Meldet das Bundle beim Kernel an.
	 *
	 * Das Bundle wird nach dem Contao-Core geladen, weil seine DCA-Dateien die
	 * Kerntabellen tl_module, tl_content, tl_layout, tl_settings, tl_user und
	 * tl_user_group ergänzen; deren Grunddefinition muss also schon vorliegen.
	 *
	 * @param ParserInterface $parser Wird nicht ausgewertet, weil das Bundle
	 *                                keine eigenen Konfigurationsdateien parst
	 *
	 * @return array<int, BundleConfig> Die Konfiguration dieses einen Bundles
	 */
	public function getBundles(ParserInterface $parser): array
	{
		return array(
			BundleConfig::create(ContaoPhotoalbumsBundle::class)
				->setLoadAfter(array(ContaoCoreBundle::class)),
		);
	}
}
