<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Album;

use Contao\FilesModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Validator;
use Schachbulle\ContaoPhotoalbumsBundle\Helper\Runtime;

/**
 * Ein einzelnes Foto und sein Weg ins Template.
 *
 * Die Klasse kapselt den Zugriff auf den Dateidatensatz und die Erzeugung des
 * skalierten Bildes. Der frueher benutzte `Controller::addImageToTemplate()`
 * ist unter Contao 5 entfallen; hier wird stattdessen der Dienst
 * `contao.image.studio` verwendet, den es in beiden Fassungen gibt.
 */
class Image
{
	/**
	 * Ein transparentes Bild von einem mal einem Bildpunkt.
	 *
	 * Wird fuer die versteckten Lightbox-Eintraege gebraucht: Dort steht im
	 * Markup nur ein Verweis auf das echte Foto, das `img`-Element selbst darf
	 * nichts laden. Die Urfassung hat dafuer eine `blank.gif` aus dem
	 * Erweiterungsverzeichnis eingebunden — ein Pfad, den es unter Contao 4 und
	 * 5 nicht mehr gibt. Als Datenadresse ist die Grafik vom Dateisystem
	 * unabhaengig.
	 *
	 * @var string
	 */
	private const BLANK_GIF = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

	/**
	 * Die UUID der Bilddatei in binaerer oder lesbarer Form.
	 *
	 * @var string|null
	 */
	private $uuid;

	/**
	 * @param mixed $uuid Die UUID der Bilddatei; alles, was keine gueltige UUID
	 *                    ist, fuehrt dazu, dass die Klasse spaeter kein Bild
	 *                    liefert
	 */
	public function __construct($uuid)
	{
		$this->uuid = Validator::isUuid($uuid) ? $uuid : null;
	}

	/**
	 * Liefert den Dateidatensatz des Fotos.
	 *
	 * Die Metadaten werden dabei entpackt auf dem Datensatz abgelegt, damit die
	 * Aufrufer ohne weitere Umwandlung `$objFile->meta['de']['title']` lesen
	 * koennen.
	 *
	 * @return FilesModel|null Der Datensatz oder null, wenn keine gueltige UUID
	 *                         vorliegt oder die Datei nicht mehr im Dateibaum
	 *                         verzeichnet ist
	 */
	public function getFile(): ?FilesModel
	{
		if (null === $this->uuid)
		{
			return null;
		}

		$objFile = FilesModel::findByUuid($this->uuid);

		if (null === $objFile)
		{
			return null;
		}

		$objFile->meta = StringUtil::deserialize($objFile->meta, true);

		return $objFile;
	}

	/**
	 * Legt das skalierte Foto in ein Template.
	 *
	 * Gesetzt werden dieselben Template-Variablen wie frueher durch
	 * `Controller::addImageToTemplate()`: `addImage`, `src`, `imgSize`,
	 * `margin`, `alt`, `caption` und `picture`. Ein bereits gesetztes `href`
	 * bleibt unangetastet — die Ansichten tragen dort den Verweis auf das
	 * Original oder auf die Detailseite ein.
	 *
	 * @param object               $objTemplate  Das Ziel-Template
	 * @param array<string, mixed> $arrMergeData Werte, die die Daten des
	 *                                           Templates ueberschreiben; hier
	 *                                           kommen `size` und `imagemargin`
	 *                                           der jeweiligen Ansicht her
	 *
	 * @return object Dasselbe Template, damit sich Aufrufe verketten lassen
	 */
	public function addToTemplate($objTemplate, array $arrMergeData = array())
	{
		$objFile = $this->getFile();

		if (null === $objFile)
		{
			return $objTemplate;
		}

		$strProjectDir = Runtime::getProjectDir();

		if (!is_file($strProjectDir.'/'.$objFile->path))
		{
			return $objTemplate;
		}

		$arrData = array_merge($objTemplate->getData(), $arrMergeData);

		$figure = System::getContainer()
			->get('contao.image.studio')
			->createFigureBuilder()
			->fromFilesModel($objFile)
			->setSize($arrData['size'] ?? null)
			->buildIfResourceExists();

		if (null !== $figure)
		{
			$figure->applyLegacyTemplateData($objTemplate, $arrData['imagemargin'] ?? null);
		}

		$this->addFileMetaDataToTemplate($objTemplate, $objFile);

		return $objTemplate;
	}

	/**
	 * Legt statt des Fotos ein leeres Ein-Punkt-Bild ins Template.
	 *
	 * Gebraucht wird das fuer die versteckten Eintraege einer Lightbox-Galerie:
	 * Das Markup muss den Verweis auf das Foto enthalten, damit die Lightbox es
	 * kennt, darf die Datei aber nicht laden.
	 *
	 * @param object $objTemplate Das Ziel-Template
	 *
	 * @return object Dasselbe Template
	 */
	public function addBlankToTemplate($objTemplate)
	{
		$objTemplate->addImage = true;
		$objTemplate->src = self::BLANK_GIF;
		$objTemplate->imgSize = ' width="1" height="1"';
		$objTemplate->margin = '';
		$objTemplate->picture = null;

		$objFile = $this->getFile();

		if (null !== $objFile)
		{
			$this->addFileMetaDataToTemplate($objTemplate, $objFile);
		}

		return $objTemplate;
	}

	/**
	 * Stellt die Metadaten der Datei als `meta` ins Template.
	 *
	 * Bevorzugt wird die aktuelle Seitensprache; gibt es dazu nichts, wird auf
	 * Englisch zurueckgefallen. Ohne Metadaten steht `meta` auf null, damit die
	 * Templates mit einer einfachen Abfrage auskommen.
	 *
	 * @param object     $objTemplate Das Ziel-Template
	 * @param FilesModel $objFile     Der Dateidatensatz
	 *
	 * @return void
	 */
	private function addFileMetaDataToTemplate($objTemplate, FilesModel $objFile): void
	{
		$objTemplate->meta = null;

		$arrMeta = StringUtil::deserialize($objFile->meta, true);
		$strLanguage = $GLOBALS['TL_LANGUAGE'] ?? 'de';

		if (isset($arrMeta[$strLanguage]))
		{
			$objTemplate->meta = $arrMeta[$strLanguage];
		}
		elseif (isset($arrMeta['en']))
		{
			$objTemplate->meta = $arrMeta['en'];
		}
	}
}
