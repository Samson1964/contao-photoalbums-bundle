<?php

declare(strict_types=1);

/*
 * Vergleicht die deutschen und englischen Sprachdateien Schluessel fuer
 * Schluessel. Gemeldet wird, was nur in einer der beiden Sprachen vorkommt und
 * was in einer Sprache leer geblieben ist.
 */

$strDir = \dirname(__DIR__).'/src/Resources/contao/languages';

/**
 * Liest eine Sprachdatei ein und liefert die flachen Schluessel.
 *
 * @param string $strFile Pfad der Sprachdatei
 *
 * @return array<string, mixed> Schluesselpfad => Wert
 */
function leseSprachdatei(string $strFile): array
{
	$GLOBALS['TL_LANG'] = array();

	include $strFile;

	$arrFlach = array();

	$fnFlatten = static function (array $arr, string $strPrefix) use (&$fnFlatten, &$arrFlach): void
	{
		foreach ($arr as $strKey => $varValue)
		{
			$strPath = $strPrefix.'['.$strKey.']';

			if (\is_array($varValue) && !isset($varValue[0]))
			{
				$fnFlatten($varValue, $strPath);

				continue;
			}

			$arrFlach[$strPath] = $varValue;
		}
	};

	$fnFlatten($GLOBALS['TL_LANG'], '');

	return $arrFlach;
}

$intProbleme = 0;

foreach (glob($strDir.'/de/*.php') as $strDe)
{
	$strEn = $strDir.'/en/'.basename($strDe);

	if (!is_file($strEn))
	{
		echo 'FEHLT: en/'.basename($strDe)."\n";
		++$intProbleme;

		continue;
	}

	$arrDe = leseSprachdatei($strDe);
	$arrEn = leseSprachdatei($strEn);

	$arrNurDe = array_diff_key($arrDe, $arrEn);
	$arrNurEn = array_diff_key($arrEn, $arrDe);

	$arrLeer = array();

	foreach ($arrEn as $strKey => $varValue)
	{
		$arrWerte = \is_array($varValue) ? $varValue : array($varValue);

		foreach ($arrWerte as $varEinzel)
		{
			if (\is_string($varEinzel) && '' === trim($varEinzel))
			{
				$arrLeer[] = $strKey;
				break;
			}
		}
	}

	/*
	 * Woertlich gleiche Werte: Ein englischer Text, der Zeichen fuer Zeichen
	 * dem deutschen gleicht, ist fast immer ein beim Nachtragen vergessener
	 * Schluessel. Genau so war `tl_content.pa2ImagesShowTeaser` monatelang
	 * deutsch geblieben, ohne dass die Pruefung auf fehlende und leere
	 * Schluessel etwas gemerkt haette.
	 *
	 * Manches ist zu Recht gleich — Eigennamen, reine Platzhalter, Abkuerzungen
	 * wie „PGN“. Deshalb ist das ein Hinweis und kein Fehler; ausgenommen sind
	 * Werte ohne einen einzigen Kleinbuchstaben-Wortteil und solche unter vier
	 * Zeichen.
	 */
	$arrGleich = array();

	foreach ($arrEn as $strKey => $varValue)
	{
		if (!isset($arrDe[$strKey]))
		{
			continue;
		}

		$arrEnWerte = \is_array($varValue) ? $varValue : array($varValue);
		$arrDeWerte = \is_array($arrDe[$strKey]) ? $arrDe[$strKey] : array($arrDe[$strKey]);

		foreach ($arrEnWerte as $intIndex => $varEinzel)
		{
			if (!\is_string($varEinzel) || !isset($arrDeWerte[$intIndex]) || !\is_string($arrDeWerte[$intIndex]))
			{
				continue;
			}

			if ($varEinzel !== $arrDeWerte[$intIndex] || mb_strlen(trim($varEinzel)) < 4)
			{
				continue;
			}

			// Mindestens zwei Woerter mit Kleinbuchstaben: dann ist es ein Satz
			if (preg_match_all('/\b\p{Ll}{2,}/u', $varEinzel) < 2)
			{
				continue;
			}

			$arrGleich[] = $strKey.'['.$intIndex.']';
		}
	}

	printf(
		"%-30s de=%-4d en=%-4d nur_de=%-3d nur_en=%-3d leer_en=%d\n",
		basename($strDe),
		\count($arrDe),
		\count($arrEn),
		\count($arrNurDe),
		\count($arrNurEn),
		\count($arrLeer)
	);

	foreach (array_keys($arrNurDe) as $strKey)
	{
		echo '    nur deutsch: '.$strKey."\n";
		++$intProbleme;
	}

	foreach (array_keys($arrNurEn) as $strKey)
	{
		echo '    nur englisch: '.$strKey."\n";
		++$intProbleme;
	}

	foreach ($arrLeer as $strKey)
	{
		echo '    englisch leer: '.$strKey."\n";
		++$intProbleme;
	}

	foreach ($arrGleich as $strKey)
	{
		echo '    wortgleich mit dem Deutschen (bitte ansehen): '.$strKey."\n";
		++$intProbleme;
	}
}

echo "\n".($intProbleme > 0 ? "$intProbleme Auffaelligkeiten\n" : "Keine Auffaelligkeiten\n");
