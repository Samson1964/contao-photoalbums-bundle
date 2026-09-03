<?php

declare(strict_types=1);

/*
 * Laufzeitpruefung des Fotoalben-Bundles.
 *
 * Anders als tools/pruefstand.php braucht dieses Werkzeug eine vollstaendige
 * Contao-Installation samt Datenbank, in der das Bundle per Composer
 * eingebunden ist. Es startet den Kernel, laedt die Datenbereiche mit den
 * echten Kern-Definitionen und ruft einzelne Rueckrufe gegen echte Datensaetze
 * auf. Damit faellt auf, was der reine Ladetest nicht sieht — etwa eine
 * Palette, die auf ein Kernfeld verweist, das es gar nicht gibt.
 *
 * Aufruf aus dem Verzeichnis der Contao-Installation heraus:
 *
 *   C:\xampp\php\php.exe <Bundle>\tools\laufzeitpruefung.php
 *
 * @license LGPL-3.0-or-later
 */

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\ManagerBundle\HttpKernel\ContaoKernel;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;

$strAppDir = getcwd();

if (!is_file($strAppDir.'/vendor/autoload.php'))
{
	fwrite(STDERR, "Bitte aus dem Wurzelverzeichnis einer Contao-Installation aufrufen.\n");
	exit(1);
}

require $strAppDir.'/vendor/autoload.php';

$intErrors = 0;
$intChecks = 0;

/**
 * Meldet das Ergebnis einer Pruefung.
 *
 * @param string $strLabel Was geprueft wurde
 * @param bool   $blnOk    Ergebnis
 * @param string $strHint  Zusatzangabe
 */
function pruefe(string $strLabel, bool $blnOk, string $strHint = ''): void
{
	global $intErrors, $intChecks;

	++$intChecks;

	if ($blnOk)
	{
		echo '  [ok]   '.$strLabel.('' !== $strHint ? ' — '.$strHint : '')."\n";

		return;
	}

	++$intErrors;
	echo '  [FEHL] '.$strLabel.('' !== $strHint ? ' — '.$strHint : '')."\n";
}

$kernel = ContaoKernel::fromInput($strAppDir, new Symfony\Component\Console\Input\ArgvInput(array('', '--env=prod')));
$kernel->boot();

$container = $kernel->getContainer();
System::setContainer($container);

// Eine Backend-Anfrage vortaeuschen, damit Scope-Abfragen sinnvoll antworten
$request = Request::create('/contao?do=photoalbums2');
$request->attributes->set('_scope', 'backend');
$container->get('request_stack')->push($request);

/** @var ContaoFramework $framework */
$framework = $container->get('contao.framework');
$framework->initialize();

echo "Laufzeitpruefung Fotoalben\n";
echo 'Contao: '.Contao\CoreBundle\ContaoCoreBundle::getVersion().'    PHP: '.PHP_VERSION."\n\n";

echo "1. Datenbereiche mit den Kern-Definitionen zusammenfuehren\n";

foreach (array('tl_photoalbums2_archive', 'tl_photoalbums2_album', 'tl_module', 'tl_content', 'tl_layout', 'tl_user', 'tl_user_group') as $strTable)
{
	try
	{
		Controller::loadDataContainer($strTable);
		System::loadLanguageFile($strTable, 'de');
		pruefe($strTable, isset($GLOBALS['TL_DCA'][$strTable]));
	}
	catch (\Throwable $e)
	{
		pruefe($strTable, false, $e->getMessage());
	}
}

System::loadLanguageFile('default', 'de');
System::loadLanguageFile('modules', 'de');

echo "\n2. Paletten und Beschriftungen\n";

pruefe('Album-Palette vollstaendig', str_contains((string) $GLOBALS['TL_DCA']['tl_photoalbums2_album']['palettes']['default'], 'startdate,enddate'));
pruefe('Modul-Palette photoalbums2', isset($GLOBALS['TL_DCA']['tl_module']['palettes']['pa2_on_one_page']));
pruefe('Element-Palette photoalbums2', isset($GLOBALS['TL_DCA']['tl_content']['palettes']['photoalbums2']));
pruefe('skipPhotoalbums2 im Layout', str_contains((string) $GLOBALS['TL_DCA']['tl_layout']['palettes']['default'], 'skipPhotoalbums2'));
pruefe('Rechte in tl_user', str_contains((string) $GLOBALS['TL_DCA']['tl_user']['palettes']['extend'], 'photoalbums2s'));
pruefe('Beschriftung Startdatum', 'Startdatum' === ($GLOBALS['TL_LANG']['tl_photoalbums2_album']['startdate'][0] ?? ''));
pruefe('Beschriftung Backend-Modul', '' !== ($GLOBALS['TL_LANG']['MOD']['photoalbums2'][0] ?? ''));

/*
 * Jedes Feld einer Palette muss auch definiert sein — sonst bricht der Data
 * Container beim Oeffnen ab.
 */
foreach (array('tl_photoalbums2_album', 'tl_photoalbums2_archive') as $strTable)
{
	foreach ($GLOBALS['TL_DCA'][$strTable]['palettes'] as $strName => $varPalette)
	{
		if ('__selector__' === $strName || !\is_string($varPalette))
		{
			continue;
		}

		foreach (explode(',', preg_replace('/\{[^}]+\}/', '', str_replace(';', ',', $varPalette))) as $strField)
		{
			$strField = trim($strField);

			if ('' === $strField)
			{
				continue;
			}

			pruefe($strTable.'.'.$strName.': '.$strField, isset($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]));
		}
	}
}

foreach (array('tl_module' => array('pa2_on_one_page', 'pa2_only_album_view', 'pa2_with_detail_page', 'photoalbums2list', 'photoalbums2view'), 'tl_content' => array('photoalbums2')) as $strTable => $arrPalettes)
{
	foreach ($arrPalettes as $strName)
	{
		$arrMissing = array();

		foreach (explode(',', preg_replace('/\{[^}]+\}/', '', str_replace(';', ',', (string) $GLOBALS['TL_DCA'][$strTable]['palettes'][$strName]))) as $strField)
		{
			$strField = trim($strField);

			if ('' !== $strField && !isset($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]))
			{
				$arrMissing[] = $strField;
			}
		}

		pruefe($strTable.'.'.$strName.': alle Felder vorhanden', empty($arrMissing), implode(', ', $arrMissing));
	}
}

echo "\n3. Datum vor 1970 im Datenbereich\n";

$objDb = Contao\Database::getInstance();
$objAlbum = $objDb->prepare("SELECT * FROM tl_photoalbums2_album WHERE startdate < 0")->limit(1)->execute();

if ($objAlbum->numRows < 1)
{
	pruefe('Testalbum mit Datum vor 1970 vorhanden', false, 'kein Datensatz mit negativem startdate gefunden');
}
else
{
	$intId = (int) $objAlbum->id;
	$strStart = (string) $objAlbum->startdate;
	$strEnd = (string) $objAlbum->enddate;

	pruefe('Startdatum in der Datenbank', '' !== $strStart && (int) $strStart < 0, $strStart);

	// Der onsubmit_callback darf das Datum nicht verbiegen
	$objDc = (new ReflectionClass(Contao\DC_Table::class))->newInstanceWithoutConstructor();
	$objRefl = new ReflectionObject($objDc);

	while ($objRefl && !$objRefl->hasProperty('intId'))
	{
		$objRefl = $objRefl->getParentClass();
	}

	$objProperty = $objRefl->getProperty('intId');
	$objProperty->setAccessible(true);
	$objProperty->setValue($objDc, $intId);

	$objListener = new Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer\AlbumListener();
	$objListener->adjustTime($objDc);

	$objAfter = $objDb->prepare('SELECT startdate, enddate FROM tl_photoalbums2_album WHERE id=?')->limit(1)->execute($intId);

	pruefe('adjustTime laesst das Startdatum stehen', $strStart === (string) $objAfter->startdate, (string) $objAfter->startdate);
	pruefe('adjustTime laesst das Enddatum stehen', $strEnd === (string) $objAfter->enddate, (string) $objAfter->enddate);
	pruefe('Datum bleibt 1968', '17.10.1968' === Contao\Date::parse('d.m.Y', (int) $objAfter->startdate), Contao\Date::parse('d.m.Y', (int) $objAfter->startdate));

	pruefe('Keine Verweisnummer mehr im Feld event', !preg_match('/^[0-9]+$/', (string) $objAlbum->event), (string) $objAlbum->event);
	pruefe('Keine Verweisnummer mehr im Feld place', !preg_match('/^[0-9]+$/', (string) $objAlbum->place), (string) $objAlbum->place);

	echo "\n4. Backend-Ausgabe der Albenliste\n";

	$strRow = $objListener->listAlbums($objAlbum->row());
	pruefe('Albumzeile enthaelt den Titel', str_contains($strRow, (string) $objAlbum->title));
	pruefe('Albumzeile enthaelt die Statusklasse', str_contains($strRow, 'cte_type'));
}

echo "\n5. Sortier-Assistent\n";

try
{
	$strClass = $GLOBALS['BE_FFL']['pa2ImageSortWizard'];
	$objWidget = new $strClass($strClass::getAttributesFromDca(
		$GLOBALS['TL_DCA']['tl_photoalbums2_album']['fields']['imageSort'],
		'imageSort',
		null,
		'imageSort',
		'tl_photoalbums2_album'
	));

	$strOutput = $objWidget->generate();
	pruefe('Assistent erzeugt Markup', \is_string($strOutput) && '' !== $strOutput);
	pruefe('Skript eingebunden', \in_array('bundles/contaophotoalbums/sortwizard.js', $GLOBALS['TL_JAVASCRIPT'] ?? array(), true));
}
catch (\Throwable $e)
{
	pruefe('Sortier-Assistent', false, $e->getMessage());
}

echo "\n6. Templates\n";

foreach (array('pa2_wrap', 'pa2_album', 'pa2_album_fluid', 'pa2_image', 'pa2_image_fluid', 'pa2_lightbox_image', 'pa2_empty') as $strTemplate)
{
	try
	{
		Contao\TemplateLoader::getPath($strTemplate, 'html5');
		pruefe($strTemplate, true);
	}
	catch (\Throwable $e)
	{
		pruefe($strTemplate, false, $e->getMessage());
	}
}

$arrGroup = Controller::getTemplateGroup('pa2_wrap');
pruefe('Templategruppe pa2_wrap gefunden', !empty($arrGroup), implode(', ', array_keys($arrGroup)));

echo "\n";
echo $intErrors > 0
	? "ERGEBNIS: $intErrors von $intChecks Pruefungen fehlgeschlagen.\n"
	: "ERGEBNIS: alle $intChecks Pruefungen bestanden.\n";

exit($intErrors > 0 ? 1 : 0);
