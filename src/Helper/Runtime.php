<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Helper;

use Contao\Config;
use Contao\Environment;
use Contao\System;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Sammelstelle fuer die Dinge, die sich zwischen Contao 4.13 und Contao 5
 * unterscheiden.
 *
 * Der alte Code von photoalbums2 hat an rund zwanzig Stellen `TL_MODE`,
 * `TL_ROOT`, `$this->Session` und `$this->log()` benutzt. Alle vier gibt es
 * unter Contao 5 nicht mehr. Statt in jeder Klasse eine eigene Weiche zu
 * bauen, laufen die Ersatzwege hier zusammen — wer spaeter eine dritte
 * Contao-Fassung bedienen muss, aendert nur diese Datei.
 */
class Runtime
{
	/**
	 * Prueft, ob der laufende Aufruf aus dem Backend kommt.
	 *
	 * Ersetzt `TL_MODE == 'BE'`. Der Dienst `contao.routing.scope_matcher` ist
	 * in beiden Contao-Fassungen oeffentlich; ohne aktuelle Anfrage (etwa im
	 * Cron-Lauf oder im Pruefstand) gilt der Aufruf als **nicht** im Backend.
	 *
	 * @return bool true, wenn die aktuelle Anfrage eine Backend-Anfrage ist
	 */
	public static function isBackend(): bool
	{
		$container = System::getContainer();

		if (null === $container || !$container->has('request_stack'))
		{
			return false;
		}

		$request = $container->get('request_stack')->getCurrentRequest();

		if (null === $request)
		{
			return false;
		}

		return $container->get('contao.routing.scope_matcher')->isBackendRequest($request);
	}

	/**
	 * Prueft, ob der laufende Aufruf aus dem Frontend kommt.
	 *
	 * Ersetzt `TL_MODE == 'FE'`. Bewusst nicht als Negation von
	 * {@see self::isBackend()} formuliert: Ein Kommandozeilenlauf ist weder
	 * Frontend noch Backend, und beide Methoden muessen dort false liefern.
	 *
	 * @return bool true, wenn die aktuelle Anfrage eine Frontend-Anfrage ist
	 */
	public static function isFrontend(): bool
	{
		$container = System::getContainer();

		if (null === $container || !$container->has('request_stack'))
		{
			return false;
		}

		$request = $container->get('request_stack')->getCurrentRequest();

		if (null === $request)
		{
			return false;
		}

		return $container->get('contao.routing.scope_matcher')->isFrontendRequest($request);
	}

	/**
	 * Prueft, ob ein Mitglied im Frontend angemeldet ist.
	 *
	 * Ersetzt die Konstante `FE_USER_LOGGED_IN`, die es unter Contao 5 nicht
	 * mehr gibt. Der Dienst `contao.security.token_checker` ist in beiden
	 * Fassungen oeffentlich und beruecksichtigt auch die Vorschau im Backend.
	 *
	 * @return bool true, wenn ein Mitglied angemeldet ist
	 */
	public static function hasFrontendUser(): bool
	{
		$container = System::getContainer();

		if (null === $container || !$container->has('contao.security.token_checker'))
		{
			return false;
		}

		return $container->get('contao.security.token_checker')->hasFrontendUser();
	}

	/**
	 * Liefert die Sitzung der laufenden Anfrage.
	 *
	 * Den Dienst `session` gibt es unter Contao 5 nicht mehr; der Weg ueber den
	 * RequestStack funktioniert in beiden Fassungen. Ohne Anfrage oder ohne
	 * gestartete Sitzung wirft Symfony eine Ausnahme — die wird hier gefangen,
	 * damit ein Cron-Lauf nicht an einer fehlenden Sitzung scheitert.
	 *
	 * @return SessionInterface|null Die Sitzung oder null, wenn es in diesem
	 *                               Zusammenhang keine gibt
	 */
	public static function getSession(): ?SessionInterface
	{
		$container = System::getContainer();

		if (null === $container || !$container->has('request_stack'))
		{
			return null;
		}

		try
		{
			return $container->get('request_stack')->getSession();
		}
		catch (\Throwable $e)
		{
			return null;
		}
	}

	/**
	 * Liefert das Wurzelverzeichnis der Contao-Installation.
	 *
	 * Ersatz fuer die unter Contao 5 entfallene Konstante `TL_ROOT`.
	 *
	 * @return string Absoluter Pfad ohne abschliessenden Schraegstrich; leer,
	 *                wenn kein Behaelter zur Verfuegung steht
	 */
	public static function getProjectDir(): string
	{
		$container = System::getContainer();

		if (null === $container)
		{
			return '';
		}

		return (string) $container->getParameter('kernel.project_dir');
	}

	/**
	 * Schreibt eine Meldung ins Contao-Protokoll.
	 *
	 * Ersatz fuer `System::log()`, das es unter Contao 5 nicht mehr gibt. Der
	 * Dienst `monolog.logger.contao.error` steht in beiden Fassungen im
	 * oeffentlichen Behaelter.
	 *
	 * @param string $strMessage Die Meldung im Klartext
	 * @param string $strMethod  Die aufrufende Methode, erscheint im Protokoll
	 *                           als Zusatzangabe
	 *
	 * @return void Fehlt der Behaelter oder der Dienst, verfaellt die Meldung
	 *              stillschweigend — ein Protokolleintrag darf den Seitenaufbau
	 *              nicht zum Absturz bringen
	 */
	public static function logError(string $strMessage, string $strMethod): void
	{
		$container = System::getContainer();

		if (null === $container || !$container->has('monolog.logger.contao.error'))
		{
			return;
		}

		$container->get('monolog.logger.contao.error')->error($strMessage, array('contao' => $strMethod));
	}

	/**
	 * Prueft, ob Adressen mit namenlosem Anhaengsel (auto_item) gebaut werden duerfen.
	 *
	 * Unter Contao 4.13 laesst sich das ueber die Systemeinstellung
	 * `useAutoItem` abschalten; unter Contao 5 gibt es die Einstellung nicht
	 * mehr, dort ist das Verfahren fest eingeschaltet. Ein fehlender Wert wird
	 * deshalb als „eingeschaltet“ gedeutet.
	 *
	 * @return bool true, wenn `/seite/albumalias` erzeugt werden darf; false,
	 *              wenn das benannte Paar `/seite/album/albumalias` noetig ist
	 */
	public static function useAutoItem(): bool
	{
		$varValue = Config::get('useAutoItem');

		return null === $varValue ? true : (bool) $varValue;
	}

	/**
	 * Liefert die Basisadresse der Installation.
	 *
	 * `Environment::get('base')` braucht in beiden Contao-Fassungen einen
	 * Dienst `request_stack` und eine laufende Anfrage. Beides fehlt beim
	 * Laden einer DCA im Kommandozeilenbetrieb und im Pruefstand, wo der
	 * Aufruf mit einer Ausnahme abbricht — deshalb hier gekapselt.
	 *
	 * @return string Die Basisadresse mit Protokoll und abschliessendem
	 *                Schraegstrich, oder eine leere Zeichenkette
	 */
	public static function getBaseUrl(): string
	{
		try
		{
			return (string) Environment::get('base');
		}
		catch (\Throwable $e)
		{
			return '';
		}
	}

	/**
	 * Ersetzt Insert-Tags in einer Zeichenkette.
	 *
	 * `Controller::replaceInsertTags()` gibt es unter Contao 5 nicht mehr; der
	 * Dienst `contao.insert_tag.parser` steht dagegen in beiden Fassungen zur
	 * Verfuegung.
	 *
	 * @param string $strText Der Text mit Insert-Tags
	 *
	 * @return string Der Text mit aufgeloesten Tags; ohne Behaelter
	 *                unveraendert
	 */
	public static function replaceInsertTags(string $strText): string
	{
		$container = System::getContainer();

		if (null === $container || !$container->has('contao.insert_tag.parser'))
		{
			return $strText;
		}

		return $container->get('contao.insert_tag.parser')->replaceInline($strText);
	}

	/**
	 * Liefert den aktuellen CSRF-Token des Backends.
	 *
	 * Ersatz fuer die unter Contao 5 entfallene Konstante `REQUEST_TOKEN`.
	 *
	 * @return string Der Token oder eine leere Zeichenkette, wenn der Dienst
	 *                nicht zur Verfuegung steht
	 */
	public static function getRequestToken(): string
	{
		$container = System::getContainer();

		if (null === $container || !$container->has('contao.csrf.token_manager'))
		{
			return '';
		}

		return (string) $container->get('contao.csrf.token_manager')->getDefaultTokenValue();
	}
}
