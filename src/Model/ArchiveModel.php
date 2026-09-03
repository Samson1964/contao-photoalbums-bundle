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

/**
 * Datenbankmodell eines Fotoalben-Archivs.
 *
 * Das Archiv ist die Elterntabelle der Alben und traegt die Einstellungen fuer
 * Kommentare, Zugriffsschutz und den RSS-/Atom-Feed. Der Tabellenname bleibt
 * bewusst `tl_photoalbums2_archive`, damit Bestandsinstallationen von
 * photoalbums2 ohne Datenumzug auf dieses Bundle wechseln koennen.
 *
 * @property int         $id
 * @property int         $pid
 * @property int         $sorting
 * @property int         $tstamp
 * @property string      $title
 * @property string      $allowComments
 * @property string      $notify
 * @property string      $sortOrder
 * @property int         $perPage
 * @property string      $moderate
 * @property string      $bbcode
 * @property string      $requireLogin
 * @property string      $disableCaptcha
 * @property string      $protected
 * @property string|null $users
 * @property string|null $groups
 * @property string      $makeFeed
 * @property string      $format
 * @property string      $language
 * @property int         $maxItems
 * @property string      $feedBase
 * @property string      $alias
 * @property int         $modulePage
 * @property string|null $description
 */
class ArchiveModel extends Model
{
	/**
	 * Name der Datenbanktabelle.
	 *
	 * @var string
	 */
	protected static $strTable = 'tl_photoalbums2_archive';
}
