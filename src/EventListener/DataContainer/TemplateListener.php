<?php

declare(strict_types=1);

/*
 * Dieses Bundle verwaltet Fotoalben und gibt sie unter Contao 4.13
 * und Contao 5 im Frontend aus.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoPhotoalbumsBundle\EventListener\DataContainer;

use Contao\Controller;

/**
 * Füllt die Auswahllisten der Templates.
 *
 * Die Methoden werden als `options_callback` in `tl_module` und `tl_content`
 * eingetragen. Ein Options-Rückruf ist hier nötig statt einer festen Liste,
 * weil eigene Templates in `templates/` erst zur Laufzeit gefunden werden.
 */
class TemplateListener
{
	/**
	 * Liefert die Templates für den Rahmen einer Ansicht.
	 *
	 * @return array<int|string, string> Die gefundenen Templates
	 */
	public function getWrapTemplates(): array
	{
		return Controller::getTemplateGroup('pa2_wrap');
	}

	/**
	 * Liefert die Templates für ein einzelnes Album in der Übersicht.
	 *
	 * @return array<int|string, string> Die gefundenen Templates
	 */
	public function getAlbumTemplates(): array
	{
		return Controller::getTemplateGroup('pa2_album');
	}

	/**
	 * Liefert die Templates für ein einzelnes Foto in der Foto-Ansicht.
	 *
	 * @return array<int|string, string> Die gefundenen Templates
	 */
	public function getImageTemplates(): array
	{
		return Controller::getTemplateGroup('pa2_image');
	}
}
