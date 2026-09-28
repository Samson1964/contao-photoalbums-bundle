<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Picker;

use Contao\CoreBundle\Picker\AbstractInsertTagPickerProvider;
use Contao\CoreBundle\Picker\DcaPickerProviderInterface;
use Contao\CoreBundle\Picker\PickerConfig;
use Contao\Database;
use Knp\Menu\FactoryInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Reiter „Fotoalben“ im Link-Picker des Backends.
 *
 * Neben Seiten, Dateien, Nachrichten, Events, FAQ und Artikeln erscheint damit
 * ein weiterer Reiter. Er öffnet die Albenliste des Backend-Moduls mit
 * Auswahlknöpfen; das gewählte Album landet als `{{photoalbum_url::ID}}` im
 * Feld. Aufgelöst wird der Tag im Frontend vom {@see
 * \Schachbulle\ContaoPhotoalbumsBundle\EventListener\InsertTagsListener}.
 *
 * **Eine Klasse für zwei Fassungen.** Die Schnittstellen haben sich zwischen
 * Contao 4.13 und Contao 5 verändert: `supportsContext()` bekam einen
 * Typhinweis `string`, `getDcaTable()` einen optionalen Parameter,
 * `convertDcaValue()` ein `mixed`. PHP erlaubt einer Kindklasse, Parameter
 * **weiter** zu fassen und optionale anzuhängen. Die Methoden hier sind deshalb
 * ohne Typhinweise und mit dem optionalen Parameter geschrieben — das ist mit
 * beiden Schnittstellen verträglich, eine engere Fassung wäre es nur mit einer.
 *
 * Auch der Konstruktor weicht vom Vorbild im Kern ab: Statt `Security` (die
 * Klasse liegt in 4.13 und 5 in verschiedenen Namensräumen) wird die
 * `AuthorizationCheckerInterface` übergeben, die es in beiden unverändert gibt.
 */
class PhotoalbumPickerProvider extends AbstractInsertTagPickerProvider implements DcaPickerProviderInterface
{
	/**
	 * Name des Reiters; zugleich der Schlüssel der Beschriftung `MSC.photoalbumPicker`.
	 *
	 * @var string
	 */
	public const NAME = 'photoalbumPicker';

	/**
	 * Prüft die Rechte des angemeldeten Backend-Benutzers.
	 *
	 * @var AuthorizationCheckerInterface
	 */
	private $objAuthorization;

	/**
	 * @param FactoryInterface              $objMenuFactory   Baut den Reiter
	 * @param RouterInterface               $objRouter        Baut die Adresse der Albenliste
	 * @param TranslatorInterface           $objTranslator    Liefert die Beschriftung
	 * @param AuthorizationCheckerInterface $objAuthorization Prüft das Modulrecht
	 */
	public function __construct(FactoryInterface $objMenuFactory, RouterInterface $objRouter, TranslatorInterface $objTranslator, AuthorizationCheckerInterface $objAuthorization)
	{
		parent::__construct($objMenuFactory, $objRouter, $objTranslator);

		$this->objAuthorization = $objAuthorization;
	}

	/**
	 * Liefert den Namen des Reiters.
	 *
	 * @return string {@see self::NAME}
	 */
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * Sagt, ob der Reiter in diesem Zusammenhang erscheint.
	 *
	 * Nur beim Verlinken, und nur für Benutzer, die das Backend-Modul
	 * „Fotoalben“ überhaupt öffnen dürfen — sonst führte der Reiter in eine
	 * Zugriffsverweigerung.
	 *
	 * @param mixed $context Der Zusammenhang, etwa `link` oder `file`
	 *
	 * @return bool true beim Verlinken mit Modulrecht
	 */
	public function supportsContext($context): bool
	{
		return 'link' === $context && $this->objAuthorization->isGranted('contao_user.modules', 'photoalbums2');
	}

	/**
	 * Sagt, ob ein vorhandener Feldwert zu diesem Reiter gehört.
	 *
	 * Steht bereits `{{photoalbum_url::5}}` im Feld, öffnet der Picker direkt
	 * diesen Reiter mit dem Album vorausgewählt.
	 *
	 * @param PickerConfig $config Die Einstellungen des Pickers samt Feldwert
	 *
	 * @return bool true, wenn der Wert ein Fotoalbum-Tag ist
	 */
	public function supportsValue(PickerConfig $config): bool
	{
		return $this->isMatchingInsertTag($config);
	}

	/**
	 * Nennt die Tabelle, deren Liste der Picker zeigt.
	 *
	 * @param PickerConfig|null $config Unter Contao 5 übergeben, unter 4.13 nicht
	 *
	 * @return string Immer `tl_photoalbums2_album`
	 */
	public function getDcaTable($config = null): string
	{
		return 'tl_photoalbums2_album';
	}

	/**
	 * Liefert die Einstellungen für die Auswahlknöpfe der Liste.
	 *
	 * @param PickerConfig $config Die Einstellungen des Pickers
	 *
	 * @return array<string, mixed> Einzelauswahl, bei vorhandenem Wert samt
	 *                              Vorauswahl und Schaltern
	 */
	public function getDcaAttributes(PickerConfig $config): array
	{
		$arrAttributes = array('fieldType' => 'radio');

		if ($this->supportsValue($config))
		{
			$arrAttributes['value'] = $this->getInsertTagValue($config);

			if ($arrFlags = $this->getInsertTagFlags($config))
			{
				$arrAttributes['flags'] = $arrFlags;
			}
		}

		return $arrAttributes;
	}

	/**
	 * Macht aus der gewählten Albumnummer den Insert-Tag.
	 *
	 * @param PickerConfig $config Die Einstellungen des Pickers
	 * @param mixed        $value  Die Nummer des gewählten Albums
	 *
	 * @return string Etwa `{{photoalbum_url::5}}`
	 */
	public function convertDcaValue(PickerConfig $config, $value): string
	{
		return sprintf($this->getInsertTag($config), $value);
	}

	/**
	 * Liefert die Adressbestandteile der Albenliste.
	 *
	 * Ist schon ein Album gewählt, öffnet der Picker gleich dessen Archiv —
	 * sonst müsste die Redaktion es bei 618 Alben in vielen Archiven erst
	 * wiederfinden.
	 *
	 * @param PickerConfig|null $config Die Einstellungen des Pickers
	 *
	 * @return array<string, mixed> Die Parameter für die Backend-Route
	 */
	protected function getRouteParameters(?PickerConfig $config = null): array
	{
		$arrParams = array('do' => 'photoalbums2');

		if (null === $config || !$config->getValue() || !$this->supportsValue($config))
		{
			return $arrParams;
		}

		$intArchiveId = $this->getArchiveId($this->getInsertTagValue($config));

		if (null !== $intArchiveId)
		{
			$arrParams['table'] = 'tl_photoalbums2_album';
			$arrParams['id'] = $intArchiveId;
		}

		return $arrParams;
	}

	/**
	 * Nennt den Tag, der eingefügt wird.
	 *
	 * @return string Die Vorlage mit Platzhalter für die Nummer
	 */
	protected function getDefaultInsertTag(): string
	{
		return '{{photoalbum_url::%s}}';
	}

	/**
	 * Ermittelt das Archiv eines Albums.
	 *
	 * Direkt über die Datenbank statt über das Modell: Der Picker läuft im
	 * Backend, und dort soll auch ein noch unveröffentlichtes Album sein Archiv
	 * finden.
	 *
	 * @param mixed $varAlbum Nummer oder Alias des Albums
	 *
	 * @return int|null Die Archivnummer oder null, wenn es das Album nicht gibt
	 */
	private function getArchiveId($varAlbum): ?int
	{
		$objResult = Database::getInstance()
			->prepare('SELECT pid FROM tl_photoalbums2_album WHERE id=? OR alias=?')
			->limit(1)
			->execute(is_numeric($varAlbum) ? (int) $varAlbum : 0, (string) $varAlbum);

		return $objResult->numRows > 0 ? (int) $objResult->pid : null;
	}
}
