<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Lädt die Dienstdefinitionen des Bundles in den Container.
 *
 * Die Basisklasse liegt bewusst unter Symfony\Component\DependencyInjection:
 * Dort gibt es sie in Symfony 5.4 (Contao 4.13) genauso wie in Symfony 7
 * (Contao 5), während die gleichnamige Klasse im HttpKernel nur noch aus
 * Gründen der Abwärtskompatibilität besteht.
 */
class ContaoPhotoalbumsExtension extends Extension
{
	/**
	 * Liest die services.yml des Bundles ein.
	 *
	 * @param array<int|string, mixed> $mergedConfig Die zusammengeführte
	 *                                               Bundle-Konfiguration; das
	 *                                               Bundle hat keine eigene
	 *                                               Semantik und wertet sie nicht aus
	 * @param ContainerBuilder         $container    Der im Aufbau befindliche Container
	 *
	 * @return void Die Methode wirft eine Ausnahme, wenn die Datei fehlt oder
	 *              fehlerhaft ist; ein Rückgabewert ist nicht vorgesehen
	 */
	public function load(array $mergedConfig, ContainerBuilder $container): void
	{
		$loader = new YamlFileLoader(
			$container,
			new FileLocator(__DIR__.'/../Resources/config')
		);

		$loader->load('services.yml');
	}
}
