<?php

declare(strict_types=1);

/*
 * Pruefstand des Fotoalben-Bundles.
 *
 * Prueft ohne Datenbank und ohne Composer-Installation des Bundles, ob sich
 * Klassen, Konfiguration, Sprachdateien und Datenbereiche unter einer
 * bestimmten Contao-Fassung ueberhaupt laden lassen. Das faengt genau die
 * Fehler ab, die ein `php -l` nicht sieht: fehlende Klassen, entfallene
 * Konstanten, falsche Elternklassen.
 *
 * Aufruf (der XAMPP-Interpreter ist noetig, die Testinstallationen sind fuer
 * PHP 8.4 gebaut):
 *
 *   C:\xampp\php\php.exe tools/pruefstand.php F:\Claude\contao-test-413
 *   C:\xampp\php\php.exe tools/pruefstand.php F:\Claude\contao-test
 *
 * @license LGPL-3.0-or-later
 */

use Contao\System;
use Symfony\Component\DependencyInjection\ContainerBuilder;

$strInstall = $argv[1] ?? '';

if ('' === $strInstall || !is_file($strInstall.'/vendor/autoload.php'))
{
	fwrite(STDERR, "Aufruf: php tools/pruefstand.php <Pfad zur Contao-Installation>\n");
	exit(1);
}

$strBundleDir = \dirname(__DIR__);

/*
 * Der eigene Autoloader muss VOR dem von Composer stehen: Liegt in der
 * Testinstallation schon eine Packagist-Fassung des Bundles, wuerde sonst die
 * geprueft und nicht der Arbeitsstand.
 */
spl_autoload_register(
	static function (string $strClass) use ($strBundleDir): void
	{
		$strPrefix = 'Schachbulle\\ContaoPhotoalbumsBundle\\';

		if (0 !== strncmp($strClass, $strPrefix, \strlen($strPrefix)))
		{
			return;
		}

		$strFile = $strBundleDir.'/src/'.str_replace('\\', '/', substr($strClass, \strlen($strPrefix))).'.php';

		if (is_file($strFile))
		{
			require_once $strFile;
		}
	},
	true,
	true
);

require $strInstall.'/vendor/autoload.php';

$intErrors = 0;
$intChecks = 0;

/**
 * Meldet das Ergebnis einer Pruefung.
 *
 * @param string $strLabel Was geprueft wurde
 * @param bool   $blnOk    Ergebnis
 * @param string $strHint  Zusatzangabe im Fehlerfall
 */
function pruefe(string $strLabel, bool $blnOk, string $strHint = ''): void
{
	global $intErrors, $intChecks;

	++$intChecks;

	if ($blnOk)
	{
		echo '  [ok]   '.$strLabel."\n";

		return;
	}

	++$intErrors;
	echo '  [FEHL] '.$strLabel.('' !== $strHint ? ' — '.$strHint : '')."\n";
}

echo "Pruefstand Fotoalben — Installation: $strInstall\n";
echo 'Contao-Fassung: '.(class_exists('Contao\CoreBundle\ContaoCoreBundle') ? \Contao\CoreBundle\ContaoCoreBundle::getVersion() : 'unbekannt')."\n";
echo 'PHP: '.PHP_VERSION."\n\n";

/*
 * Ein leerer Behaelter genuegt: Die eigenen Klassen fragen ihn nur nach
 * Diensten, die sie im Zweifel auch entbehren koennen.
 */
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $strInstall.'/public/index.php';

$container = new ContainerBuilder();
$container->setParameter('kernel.debug', false);
$container->setParameter('kernel.project_dir', $strInstall);
$container->setParameter('contao.web_dir', $strInstall.'/public');
$container->setParameter('contao.image.valid_extensions', array('jpg', 'jpeg', 'png', 'gif', 'webp'));
$container->compile();
System::setContainer($container);

echo "1. Klassen\n";

$arrClasses = array(
	'Schachbulle\ContaoPhotoalbumsBundle\ContaoPhotoalbumsBundle',
	'Schachbulle\ContaoPhotoalbumsBundle\ContaoManager\Plugin',
	'Schachbulle\ContaoPhotoalbumsBundle\DependencyInjection\ContaoPhotoalbumsExtension',
	'Schachbulle\ContaoPhotoalbumsBundle\Model\AlbumModel',
	'Schachbulle\ContaoPhotoalbumsBundle\Model\ArchiveModel',
	'Schachbulle\ContaoPhotoalbumsBundle\Album\ItemList',
	'Schachbulle\ContaoPhotoalbumsBundle\Album\Album',
	'Schachbulle\ContaoPhotoalbumsBundle\Album\Archive',
	'Schachbulle\ContaoPhotoalbumsBundle\Album\Image',
	'Schachbulle\ContaoPhotoalbumsBundle\Sorter\FileSorter',
	'Schachbulle\ContaoPhotoalbumsBundle\Sorter\ImageSorter',
	'Schachbulle\ContaoPhotoalbumsBundle\Sorter\AlbumSorter',
	'Schachbulle\ContaoPhotoalbumsBundle\Parser\ViewParser',
	'Schachbulle\ContaoPhotoalbumsBundle\Parser\AlbumViewParser',
	'Schachbulle\ContaoPhotoalbumsBundle\Parser\ImageViewParser',
	'Schachbulle\ContaoPhotoalbumsBundle\Helper\Runtime',
	'Schachbulle\ContaoPhotoalbumsBundle\Helper\Assets',
	'Schachbulle\ContaoPhotoalbumsBundle\Helper\Palette',
	'Schachbulle\ContaoPhotoalbumsBundle\Helper\Pagination',
	'Schachbulle\ContaoPhotoalbumsBundle\Helper\PreviewImage',
	'Schachbulle\ContaoPhotoalbumsBundle\Helper\TimeFilter',
	'Schachbulle\ContaoPhotoalbumsBundle\Helper\Thumbnail',
	'Schachbulle\ContaoPhotoalbumsBundle\Helper\EmptyTemplate',
	'Schachbulle\ContaoPhotoalbumsBundle\Widget\ImageSortWizard',
	'Schachbulle\ContaoPhotoalbumsBundle\Widget\SortWizard',
	'Schachbulle\ContaoPhotoalbumsBundle\Modules\ModulePhotoalbums2',
	'Schachbulle\ContaoPhotoalbumsBundle\Modules\ModulePhotoalbums2List',
	'Schachbulle\ContaoPhotoalbumsBundle\Modules\ModulePhotoalbums2View',
	'Schachbulle\ContaoPhotoalbumsBundle\Elements\ContentPhotoalbums2',
	'Schachbulle\ContaoPhotoalbumsBundle\Feed\FeedGenerator',
	'Schachbulle\ContaoPhotoalbumsBundle\Migration\TranslationFieldsMigration',
	'Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer\AlbumListener',
	'Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer\ArchiveListener',
	'Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer\ModuleListener',
	'Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer\ContentListener',
	'Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer\TemplateListener',
	'Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer\TimeFilterListener',
	'Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer\FeedListener',
);

foreach ($arrClasses as $strClass)
{
	try
	{
		pruefe($strClass, class_exists($strClass));
	}
	catch (\Throwable $e)
	{
		pruefe($strClass, false, $e->getMessage());
	}
}

echo "\n2. Sprachdateien\n";

foreach (array('de', 'en') as $strLanguage)
{
	foreach (glob($strBundleDir.'/src/Resources/contao/languages/'.$strLanguage.'/*.php') as $strFile)
	{
		try
		{
			include $strFile;
			pruefe($strLanguage.'/'.basename($strFile), true);
		}
		catch (\Throwable $e)
		{
			pruefe($strLanguage.'/'.basename($strFile), false, $e->getMessage());
		}
	}
}

echo "\n3. Konfiguration\n";

try
{
	include $strBundleDir.'/src/Resources/contao/config/config.php';
	pruefe('config.php geladen', true);
}
catch (\Throwable $e)
{
	pruefe('config.php geladen', false, $e->getMessage());
}

pruefe('BE_MOD-Eintrag vorhanden', isset($GLOBALS['BE_MOD']['content']['photoalbums2']));
pruefe('Drei Frontend-Module', 3 === \count($GLOBALS['FE_MOD']['photoalbums2_legend'] ?? array()));
pruefe('Inhaltselement registriert', isset($GLOBALS['TL_CTE']['media']['photoalbums2']));

foreach (($GLOBALS['FE_MOD']['photoalbums2_legend'] ?? array()) as $strType => $strClass)
{
	pruefe('Modulklasse '.$strType, class_exists($strClass));
}

pruefe('Elementklasse', class_exists($GLOBALS['TL_CTE']['media']['photoalbums2'] ?? ''));
pruefe('Widget pa2ImageSortWizard', class_exists($GLOBALS['BE_FFL']['pa2ImageSortWizard'] ?? ''));
pruefe('Widget pa2SortWizard', class_exists($GLOBALS['BE_FFL']['pa2SortWizard'] ?? ''));

echo "\n4. Datenbereiche\n";

/*
 * Die Kerntabellen werden nur so weit vorbelegt, wie die eigenen Ergaenzungen
 * es brauchen. Ein echtes loadDataContainer() waere ohne Kernel nicht moeglich.
 */
$GLOBALS['TL_DCA']['tl_module'] = array(
	'palettes' => array('__selector__' => array('type')),
	'fields'   => array('name' => array('eval' => array())),
);
$GLOBALS['TL_DCA']['tl_content'] = array(
	'palettes' => array('__selector__' => array('type')),
	'fields'   => array(),
);
$GLOBALS['TL_DCA']['tl_layout'] = array(
	'palettes' => array('default' => '{title_legend},name;{style_legend},framework,external,combineScripts'),
	'fields'   => array(),
);
$GLOBALS['TL_DCA']['tl_settings'] = array(
	'palettes' => array('default' => '{global_legend},dateFormat'),
	'fields'   => array(),
);
$GLOBALS['TL_DCA']['tl_user'] = array(
	'palettes' => array(
		'extend' => '{name_legend},username;{filemounts_legend},filemounts,fop;{account_legend},disable',
		'custom' => '{name_legend},username;{filemounts_legend},filemounts,fop;{account_legend},disable',
	),
	'fields' => array(),
);
$GLOBALS['TL_DCA']['tl_user_group'] = array(
	'palettes' => array('default' => '{name_legend},name;{filemounts_legend},filemounts,fop;{account_legend},disable'),
	'fields'   => array(),
);

foreach (glob($strBundleDir.'/src/Resources/contao/dca/*.php') as $strFile)
{
	try
	{
		include $strFile;
		pruefe(basename($strFile), true);
	}
	catch (\Throwable $e)
	{
		pruefe(basename($strFile), false, $e->getMessage());
	}
}

pruefe('Album-Tabelle definiert', isset($GLOBALS['TL_DCA']['tl_photoalbums2_album']['fields']['startdate']));
pruefe('startdate ist varchar(11)', str_starts_with((string) ($GLOBALS['TL_DCA']['tl_photoalbums2_album']['fields']['startdate']['sql'] ?? ''), 'varchar(11)'), (string) ($GLOBALS['TL_DCA']['tl_photoalbums2_album']['fields']['startdate']['sql'] ?? ''));
pruefe('Kein translation-fields-Rueckruf mehr', !str_contains(serialize($GLOBALS['TL_DCA']['tl_photoalbums2_album']), 'TranslationFieldsHelper'));
pruefe('Palette photoalbums2 vorhanden', isset($GLOBALS['TL_DCA']['tl_module']['palettes']['photoalbums2']));
pruefe('skipPhotoalbums2 in tl_layout', str_contains((string) $GLOBALS['TL_DCA']['tl_layout']['palettes']['default'], 'skipPhotoalbums2'));
pruefe('photoalbums2s in tl_user (extend)', str_contains((string) $GLOBALS['TL_DCA']['tl_user']['palettes']['extend'], 'photoalbums2s'));
pruefe('photoalbums2s in tl_user_group', str_contains((string) $GLOBALS['TL_DCA']['tl_user_group']['palettes']['default'], 'photoalbums2s'));

echo "\n5. Rueckrufe aus den Datenbereichen\n";

$arrCallbacks = array();

/**
 * Sammelt alle Rueckrufe aus einer DCA-Teilstruktur ein.
 *
 * @param mixed                       $varNode Ein Ausschnitt der DCA
 * @param array<int, array<int, mixed>> $arrOut  Sammelbehaelter
 */
function sammleRueckrufe($varNode, array &$arrOut): void
{
	if (!\is_array($varNode))
	{
		return;
	}

	if (2 === \count($varNode) && isset($varNode[0], $varNode[1]) && \is_string($varNode[0]) && \is_string($varNode[1]) && str_starts_with($varNode[0], 'Schachbulle\\'))
	{
		$arrOut[] = $varNode;

		return;
	}

	foreach ($varNode as $varChild)
	{
		sammleRueckrufe($varChild, $arrOut);
	}
}

foreach (array('tl_photoalbums2_album', 'tl_photoalbums2_archive', 'tl_module', 'tl_content') as $strTable)
{
	sammleRueckrufe($GLOBALS['TL_DCA'][$strTable] ?? array(), $arrCallbacks);
}

$arrSeen = array();

foreach ($arrCallbacks as $arrCallback)
{
	$strKey = $arrCallback[0].'::'.$arrCallback[1];

	if (isset($arrSeen[$strKey]))
	{
		continue;
	}

	$arrSeen[$strKey] = true;

	pruefe($strKey, class_exists($arrCallback[0]) && method_exists($arrCallback[0], $arrCallback[1]));
}

pruefe('Mindestens zehn Rueckrufe gefunden', \count($arrSeen) >= 10, 'gefunden: '.\count($arrSeen));

echo "\n6. Zeitrechnung mit Daten vor 1970\n";

$intStart = mktime(0, 0, 0, 10, 17, 1968);
$intEnd = mktime(0, 0, 0, 11, 7, 1968);

pruefe('Startdatum negativ', \is_int($intStart) && $intStart < 0, (string) $intStart);
pruefe('Contao\Date formatiert 1968', '17.10.1968' === \Contao\Date::parse('d.m.Y', $intStart), \Contao\Date::parse('d.m.Y', $intStart));
pruefe('Enddatum nach Startdatum', $intEnd > $intStart);
pruefe('Zeitstempel passt in varchar(11)', \strlen((string) $intStart) <= 11, (string) \strlen((string) $intStart));

/*
 * Der eigentliche Fehler steckte in der Ansicht: Sie hat mit "> 0" geprueft.
 * Hier wird die geerbte Methode ueber Reflection direkt aufgerufen.
 */
$objTemplate = new \stdClass();
$objParser = (new \ReflectionClass(\Schachbulle\ContaoPhotoalbumsBundle\Parser\AlbumViewParser::class))->newInstanceWithoutConstructor();
$objMethod = new \ReflectionMethod(\Schachbulle\ContaoPhotoalbumsBundle\Parser\ViewParser::class, 'addDateToTemplate');
$objMethod->setAccessible(true);
$objMethod->invoke($objParser, $objTemplate, $intStart, $intEnd);

pruefe('Ansicht gibt den Zeitraum aus', '17.10.1968 - 07.11.1968' === ($objTemplate->date ?? ''), (string) ($objTemplate->date ?? '(leer)'));

$objTemplate2 = new \stdClass();
$objMethod->invoke($objParser, $objTemplate2, $intStart, 0);
pruefe('Ohne Enddatum nur das Startdatum', '17.10.1968' === ($objTemplate2->date ?? ''), (string) ($objTemplate2->date ?? '(leer)'));

echo "\n7. Sortierung und Zeitfilter\n";

$objSorter = new \Schachbulle\ContaoPhotoalbumsBundle\Sorter\ImageSorter('name_asc', array(), array());
pruefe('ImageSorter mit leerer Liste', array() === $objSorter->getSortedUuids());

$objAlbumSorter = new \Schachbulle\ContaoPhotoalbumsBundle\Sorter\AlbumSorter('custom', array(3, 1, 2), array(2, 1));
pruefe('AlbumSorter eigene Reihenfolge', array(2, 1, 3) === $objAlbumSorter->getSortedIds(), implode(',', $objAlbumSorter->getSortedIds()));

$objFilter = new \Schachbulle\ContaoPhotoalbumsBundle\Helper\TimeFilter(
	array('unit' => 'days', 'value' => 10),
	array('unit' => 'days', 'value' => 0)
);
pruefe('Zeitfilter: heute bleibt drin', !$objFilter->doFilter(time(), time()));
pruefe('Zeitfilter: 1968 faellt heraus', $objFilter->doFilter($intStart, $intEnd));

$objOhneFilter = new \Schachbulle\ContaoPhotoalbumsBundle\Helper\TimeFilter('', '');
pruefe('Ohne Filter faellt nichts heraus', !$objOhneFilter->doFilter($intStart, $intEnd));

echo "\n8. Erkennung der Verweisnummern in der Migration\n";

/*
 * extractReference() entscheidet, ob ein Feldwert ein Verweis auf
 * tl_translation_fields ist. Zu grosszuegig hiesse: echte Texte werden
 * geleert. Zu streng hiesse: eine Nummer bleibt im Frontend stehen — genau
 * das war bei einer im Editor gespeicherten Beschreibung der Fall
 * (`<p>2071</p>`). Die Faelle sind deshalb hier festgeschrieben.
 */
$objMigrationRefl = new \ReflectionClass(\Schachbulle\ContaoPhotoalbumsBundle\Migration\TranslationFieldsMigration::class);
$objMigration = $objMigrationRefl->newInstanceWithoutConstructor();
$objExtract = $objMigrationRefl->getMethod('extractReference');
$objExtract->setAccessible(true);

$arrCases = array(
	// Wert                             erwartete Nummer (null = kein Verweis)
	array('2071', 2071),
	array('<p>2071</p>', 2071),
	array("  <p>  2071  </p>\n", 2071),
	array('<p>&nbsp;2071</p>', 2071),
	array('<div><p>2071</p></div>', 2071),
	array('<p><strong>2071</strong></p>', 2071),
	array('', null),
	array('0', null),
	array('<p>0</p>', null),
	array('1968er Jahrgang', null),
	array('<p>Siehe 2071 Fotos</p>', null),
	array('2071 und 2072', null),
	array('20.71', null),
	array('Berlin, Hauptbahnhof', null),
	array('<p>Turnierseite: <a href="{{link_url::466}}">Maenner</a></p>', null),
	array('Frank Hoppe', null),
);

foreach ($arrCases as $arrCase)
{
	$varResult = $objExtract->invoke($objMigration, $arrCase[0]);
	$strLabel = '' === $arrCase[0] ? '(leer)' : str_replace("\n", '\n', $arrCase[0]);

	pruefe(
		sprintf('%-52s -> %s', $strLabel, null === $arrCase[1] ? 'kein Verweis' : $arrCase[1]),
		$varResult === $arrCase[1],
		null === $varResult ? 'kein Verweis' : (string) $varResult
	);
}

echo "\n9. Dienstdefinitionen\n";

try
{
	$objDiContainer = new ContainerBuilder();
	$objDiContainer->setParameter('kernel.debug', false);
	$objDiContainer->setParameter('kernel.project_dir', $strInstall);

	// Abhaengigkeiten, die der Kernel sonst mitbringt
	$objDiContainer->register('contao.framework', \Contao\CoreBundle\Framework\ContaoFramework::class)->setSynthetic(true);
	$objDiContainer->register('database_connection', \Doctrine\DBAL\Connection::class)->setSynthetic(true);
	$objDiContainer->setAlias(\Contao\CoreBundle\Framework\ContaoFramework::class, 'contao.framework');
	$objDiContainer->setAlias(\Doctrine\DBAL\Connection::class, 'database_connection');

	$objExtension = new \Schachbulle\ContaoPhotoalbumsBundle\DependencyInjection\ContaoPhotoalbumsExtension();
	$objExtension->load(array(), $objDiContainer);

	pruefe('services.yml geladen', true);
	pruefe('Cron-Auftrag registriert', $objDiContainer->hasDefinition(\Schachbulle\ContaoPhotoalbumsBundle\Feed\FeedGenerator::class));
	pruefe('Migration registriert', $objDiContainer->hasDefinition(\Schachbulle\ContaoPhotoalbumsBundle\Migration\TranslationFieldsMigration::class));

	$objDiContainer->compile();
	pruefe('Behaelter uebersetzt', true);
}
catch (\Throwable $e)
{
	pruefe('Dienstdefinitionen', false, $e->getMessage());
}

pruefe('Migration ist eine Contao-Migration', is_subclass_of(\Schachbulle\ContaoPhotoalbumsBundle\Migration\TranslationFieldsMigration::class, \Contao\CoreBundle\Migration\MigrationInterface::class));
pruefe('AsCronJob am FeedGenerator', array() !== (new \ReflectionClass(\Schachbulle\ContaoPhotoalbumsBundle\Feed\FeedGenerator::class))->getAttributes(\Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob::class));

echo "\n";
echo $intErrors > 0
	? "ERGEBNIS: $intErrors von $intChecks Pruefungen fehlgeschlagen.\n"
	: "ERGEBNIS: alle $intChecks Pruefungen bestanden.\n";

exit($intErrors > 0 ? 1 : 0);
