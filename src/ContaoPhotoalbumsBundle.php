<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle-Klasse der Fotoalben.
 *
 * Die Klasse bleibt leer, weil das Bundle weder eigene Compiler-Pässe noch
 * abweichende Verzeichnisse braucht. Symfony leitet den Bundle-Namen aus dem
 * Klassennamen ab; daraus ergibt sich auch der öffentliche Pfad der Dateien
 * aus Resources/public, nämlich "bundles/contaophotoalbums".
 */
class ContaoPhotoalbumsBundle extends Bundle
{
}
