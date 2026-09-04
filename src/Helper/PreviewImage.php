<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Helper;

use Contao\FilesModel;
use Contao\StringUtil;
use Schachbulle\ContaoPhotoalbumsBundle\Model\AlbumModel;
use Schachbulle\ContaoPhotoalbumsBundle\Sorter\FileSorter;

/**
 * Bestimmt das Vorschaubild eines Albums.
 *
 * Die Entscheidung faellt in zwei Stufen: Das Modul legt fest, ob es die
 * Einstellung des Albums uebernimmt oder sie uebersteuert; das Album selbst
 * legt fest, ob es gar kein Vorschaubild gibt, ein zufaelliges, das erste oder
 * ein von Hand ausgewaehltes.
 */
class PreviewImage
{
	/**
	 * Der Albumdatensatz.
	 *
	 * @var AlbumModel|null
	 */
	private $objAlbum;

	/**
	 * Die Einstellung des Moduls aus dem Feld `pa2PreviewImage`.
	 *
	 * @var string
	 */
	private $strType;

	/**
	 * Die ermittelte UUID des Vorschaubildes.
	 *
	 * @var string|null
	 */
	private $uuid;

	/**
	 * @param mixed $varAlbum Albumnummer oder Albumdatensatz
	 * @param mixed $strType  Einstellung des Moduls, etwa `use_album_options`
	 *                        oder `first_image_at_no_preview_images`
	 */
	public function __construct($varAlbum, $strType)
	{
		if (is_numeric($varAlbum))
		{
			$varAlbum = AlbumModel::findByPk($varAlbum);
		}

		$this->objAlbum = $varAlbum instanceof AlbumModel ? $varAlbum : null;
		$this->strType = \is_string($strType) ? $strType : '';

		$this->setPreviewImageUuid();
	}

	/**
	 * Wertet die Moduleinstellung aus und legt die UUID fest.
	 *
	 * @return void Setzt ausschliesslich die Eigenschaft $uuid
	 */
	private function setPreviewImageUuid(): void
	{
		if (null === $this->objAlbum)
		{
			$this->uuid = null;

			return;
		}

		switch ($this->strType)
		{
			case 'no_preview_images':
				$this->uuid = null;
				break;

			case 'random_images':
				$this->uuid = $this->getRandomImage();
				break;

			case 'random_images_at_no_preview_images':
				$this->uuid = 'no_preview_image' === $this->objAlbum->previewImageType
					? $this->getRandomImage()
					: $this->getImageByAlbumType();
				break;

			case 'first_image_at_no_preview_images':
				$this->uuid = 'no_preview_image' === $this->objAlbum->previewImageType
					? $this->getFirstImage()
					: $this->getImageByAlbumType();
				break;

			case 'use_album_options':
			default:
				$this->uuid = $this->getImageByAlbumType();
				break;
		}
	}

	/**
	 * Wertet die Einstellung des Albums aus.
	 *
	 * @return string|null Die UUID des Vorschaubildes oder null, wenn das Album
	 *                     ausdruecklich keines haben soll
	 */
	private function getImageByAlbumType(): ?string
	{
		switch ($this->objAlbum->previewImageType)
		{
			case 'random_preview_image':
				return $this->getRandomImage();

			case 'first_preview_image':
				return $this->getFirstImage();

			case 'select_preview_image':
				return $this->objAlbum->previewImage;

			case 'no_preview_image':
			default:
				return null;
		}
	}

	/**
	 * Liefert die UUID des Vorschaubildes.
	 *
	 * @return string|null Die UUID oder null, wenn kein Vorschaubild gezeigt
	 *                     werden soll
	 */
	public function getPreviewImageUuid(): ?string
	{
		return $this->uuid;
	}

	/**
	 * Liefert den Dateidatensatz des Vorschaubildes.
	 *
	 * @return FilesModel|null Der Datensatz oder null, wenn es kein
	 *                         Vorschaubild gibt oder die Datei fehlt
	 */
	public function getPreviewImage(): ?FilesModel
	{
		if (null === $this->uuid)
		{
			return null;
		}

		return FilesModel::findByUuid($this->uuid);
	}

	/**
	 * Zieht ein zufaelliges Foto des Albums.
	 *
	 * @return string|null Die UUID oder null, wenn das Album keine Fotos hat
	 */
	private function getRandomImage(): ?string
	{
		$arrImages = $this->getAlbumImageUuids();

		if (empty($arrImages))
		{
			return null;
		}

		return $arrImages[random_int(0, \count($arrImages) - 1)];
	}

	/**
	 * Nimmt das erste Foto des Albums.
	 *
	 * @return string|null Die UUID oder null, wenn das Album keine Fotos hat
	 */
	private function getFirstImage(): ?string
	{
		$arrImages = $this->getAlbumImageUuids();

		if (empty($arrImages))
		{
			return null;
		}

		return $arrImages[0];
	}

	/**
	 * Loest die Bildauswahl des Albums in eine reine Dateiliste auf.
	 *
	 * Im Feld `images` koennen auch Ordner stehen; der {@see FileSorter} steigt
	 * in sie hinab und beruecksichtigt dabei die zugelassenen Dateiendungen.
	 *
	 * Gesucht wird zuerst unter den **Fotos**: Als Aufmacher eines Albums ist
	 * ein echtes Bild allemal besser als die Platzhalterkachel eines Videos.
	 * Erst wenn das Album ueberhaupt kein Foto enthaelt, kommen die Videos in
	 * Betracht — dann bleibt nur der Platzhalter.
	 *
	 * @return array<int, string> Die UUIDs der in Frage kommenden Dateien
	 */
	private function getAlbumImageUuids(): array
	{
		$arrImages = StringUtil::deserialize($this->objAlbum->images, true);

		if (empty($arrImages))
		{
			return array();
		}

		$objFileSorter = new FileSorter($arrImages, $GLOBALS['pa2']['imageExtensions'] ?? null);
		$arrFound = $objFileSorter->getImageUuids();

		if (!empty($arrFound))
		{
			return $arrFound;
		}

		$objFileSorter = new FileSorter($arrImages, $GLOBALS['pa2']['mediaExtensions'] ?? null);

		return $objFileSorter->getImageUuids();
	}
}
