<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\Model;

use Contao\Model;
use Contao\Model\Collection;

/**
 * Datenbankmodell eines einzelnen Fotoalbums.
 *
 * Der Tabellenname bleibt bewusst `tl_photoalbums2_album`, damit
 * Bestandsinstallationen von photoalbums2 ohne Datenumzug auf dieses Bundle
 * wechseln koennen. Die Felder `startdate` und `enddate` sind Unix-Zeitstempel
 * und duerfen ausdruecklich **negativ** sein (Aufnahmen vor 1970).
 *
 * @property int         $id
 * @property int         $pid
 * @property int         $sorting
 * @property int         $tstamp
 * @property string      $title
 * @property string      $alias
 * @property int         $author
 * @property string      $startdate
 * @property string      $enddate
 * @property string|null $images
 * @property string      $imageSortType
 * @property string|null $imageSort
 * @property string      $previewImageType
 * @property string|null $previewImage
 * @property string      $event
 * @property string      $place
 * @property string      $photographer
 * @property string|null $description
 * @property string      $protected
 * @property string|null $users
 * @property string|null $groups
 * @property string      $cssClass
 * @property string      $noComments
 * @property string      $published
 * @property string      $start
 * @property string      $stop
 */
class AlbumModel extends Model
{
	/**
	 * Name der Datenbanktabelle.
	 *
	 * @var string
	 */
	protected static $strTable = 'tl_photoalbums2_album';

	/**
	 * Liefert alle veroeffentlichten Alben mehrerer Archive.
	 *
	 * Die Auswahl beruecksichtigt das Veroeffentlichungsfenster (`start`/`stop`)
	 * und sortiert nach Archiv und der im Backend festgelegten Reihenfolge.
	 * Der Zugriffsschutz wird hier **nicht** geprueft; das erledigt
	 * {@see \Schachbulle\ContaoPhotoalbumsBundle\Album\Album}.
	 *
	 * @param array<int, int|string>|null $arrIds Datensatznummern der Archive;
	 *                                            leer oder kein Feld ergibt null
	 *
	 * @return Collection|null Die gefundenen Alben oder null, wenn keine
	 *                         Archivnummern uebergeben wurden oder es zu ihnen
	 *                         keine veroeffentlichten Alben gibt
	 */
	public static function findAlbumsByMultipleArchives($arrIds): ?Collection
	{
		if (!\is_array($arrIds) || empty($arrIds))
		{
			return null;
		}

		$strIds = implode(',', array_map('intval', $arrIds));

		$time = time();
		$t = static::$strTable;

		return static::findBy(
			array("$t.pid IN(".$strIds.") AND ($t.start='' OR $t.start<'$time') AND ($t.stop='' OR $t.stop>'$time') AND $t.published='1'"),
			null,
			array('order' => "$t.pid, $t.sorting")
		);
	}

	/**
	 * Sucht ein veroeffentlichtes Album ueber seine Nummer oder seinen Alias.
	 *
	 * Der Aufrufer weiss meist nicht, ob im Adressfragment eine Nummer oder ein
	 * Alias steht; deshalb wird beides in einer Abfrage geprueft. Nicht
	 * numerische Werte werden fuer den Nummernvergleich zu 0, damit MySQL nicht
	 * stillschweigend jeden Text auf 0 castet und dadurch Album 0 trifft.
	 *
	 * @param mixed $value Datensatznummer oder Alias
	 *
	 * @return Collection|null Eine Sammlung mit hoechstens einem Album oder null,
	 *                         wenn nichts Passendes veroeffentlicht ist
	 */
	public static function findPublishedByIdOrAlias($value): ?Collection
	{
		$t = static::$strTable;
		$time = time();

		$arrOptions = array(
			'limit'  => 1,
			'column' => array("($t.id=? OR $t.alias=?) AND ($t.start='' OR $t.start<$time) AND ($t.stop='' OR $t.stop>$time) AND $t.published='1'"),
			'value'  => array((is_numeric($value) ? $value : 0), $value),
			'return' => 'Collection',
		);

		return static::find($arrOptions);
	}
}
