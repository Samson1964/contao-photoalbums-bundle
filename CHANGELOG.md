# Fotoalben Changelog

## Version 1.0.0 (2026-09-03)

Erste Fassung des Bundles. Es tritt die Nachfolge von
`schachbulle/contao-photoalbums2` an, das seinerseits ein Fork von
`craffft/contao-photoalbums2` war. Der gesamte Altbestand aus
`system/modules/photoalbums2` wurde auf die Bundle-Struktur, auf Namensräume
und auf die heutigen Contao-Schnittstellen umgestellt.

* Add: Unterstützung für Contao 5 neben Contao 4.13
  (`contao/core-bundle: ^4.13 || ^5.0`, PHP `^7.4 || ^8.0`, geprüft unter
  PHP 8.4.24 gegen Contao 4.13.58 und 5.7.7).
* Add: Die Sortier-Assistenten aus `craffft/contao-imagesortwizard` und
  `craffft/contao-sortwizard` sind fest eingebaut. Beide Fremdpakete werden
  nicht mehr gebraucht.
* Add: Migration „Fotoalben: Texte aus tl_translation_fields in die Felder
  zurücknehmen“. Sie ersetzt in `tl_photoalbums2_album.event`, `.place`,
  `.photographer` und `.description` sowie in `tl_content.pa2Teaser` und
  `tl_module.pa2Teaser` die Verweisnummer durch den zugehörigen Text. Genau
  diese Nummern erschienen bisher im Backend und im Frontend anstelle der
  Texte. Die Tabelle `tl_translation_fields` selbst bleibt unangetastet.
* Fix: **Aufnahmedaten vor 1970 lassen sich eintragen und werden angezeigt.**
  Zwei Stellen waren schuld. Beim Speichern prüfte
  `tl_photoalbums2_album::adjustTime()` mit `$startdate < 1` und ersetzte damit
  jeden negativen Zeitstempel durch das heutige Datum; geprüft wird jetzt auf
  eine leere Angabe. In der Ansicht verwarf `addDateToTemplate()` mit
  `$intStartdate > 0` jedes negative Datum, so dass im Frontend gar keines
  erschien. Zusätzlich sind `startdate` und `enddate` von `varchar(10)` auf
  `varchar(11)` erweitert, weil das Minuszeichen eine Stelle braucht.
* Change: Die Mehrsprachigkeit über `craffft/contao-translation-fields`
  entfällt ersatzlos. Die betroffenen Felder führen wieder den Text selbst;
  `tl_content.pa2Teaser` wechselt dabei von einer Ganzzahl- auf eine
  Textspalte.
* Change: Die Frontend-Module und das Inhaltselement hängen weiterhin in
  `$GLOBALS['FE_MOD']` und `$GLOBALS['TL_CTE']`, jetzt aber mit dem vollen
  Klassennamen — das funktioniert in beiden Contao-Fassungen.
* Change: Die DCA-Rückrufe stecken nicht mehr als globale Klassen in den
  DCA-Dateien, sondern in eigenen Klassen unter `src/EventListener/DataContainer`.
  Das umgeht die Falle, dass Contao 5 zusammengeführte DCA-Dateien samt
  Rückrufklasse zwischenspeichert.
* Fix: Die Migration blieb dauerhaft als „ausstehend" stehen und der
  Installationsassistent lief im Kreis: Er bot die Migration an, meldete
  „Es waren keine Verweise auf tl_translation_fields zu ersetzen." und bot sie
  danach erneut an. Ursache war, dass `shouldRun()` einen Verweis schon dann
  als Arbeit zählte, wenn es die Zeile in `tl_translation_fields` überhaupt
  gab, während `run()` nur bei **nicht leerem** Text ersetzte. Beide benutzen
  jetzt dieselbe Auswertung. Zusätzlich bleibt ein Verweis unangetastet, dessen
  Text selbst wieder wie eine Verweisnummer aussieht — sonst hätte die Migration
  beim nächsten Lauf erneut zugeschlagen.
* Change: Die Migration meldet am Ende, zu wie vielen Verweisen kein Text
  hinterlegt ist und wie viele auf eine weitere Nummer zeigen. In diesen
  Feldern bleibt die Nummer stehen, und jemand muss von Hand nachsehen.
* Change: Der `replace`-Eintrag auf `contao-legacy/photoalbums2` ist entfallen.
  Er stammte aus der Urfassung und führte dazu, dass Composer beim Umstieg
  „They all replace contao-legacy/photoalbums2” meldete — also ein Paket aus
  Contao-3-Zeiten nannte statt des tatsächlich installierten
  `schachbulle/contao-photoalbums2`. Die Sperre selbst bleibt über `conflict`
  bestehen, die Meldung nennt jetzt das richtige Paket.
* Fix: Im Modus „Nur Album-Ansicht mit Lightbox” stand das erste Foto zweimal
  in der Bildergalerie. Sowohl der Titel als auch das Vorschaubild trugen den
  Lightbox-Verweis, und beide zeigten auf dasselbe Foto. Jetzt trägt nur ein
  sichtbares Element den Verweis: das Vorschaubild, und nur wenn es keines gibt,
  der Titel.
* Fix: Der Foto-Sortier-Assistent hat die Reihenfolge beim Speichern
  weggeworfen. `Widget::validator()` ruft sich bei einem Feld über `array_map`
  für jeden Eintrag selbst auf; die eigene Fassung bekam dadurch statt des
  Feldes eine einzelne UUID und lieferte ein leeres Feld zurück. In der
  Datenbank stand danach ein Feld aus leeren Feldern. Die Prüfung läuft jetzt
  ohne Aufruf der Elternfassung.
* Change: Die Sortier-Assistenten sortieren im Browser statt über
  Adressparameter mit sofortigem Datenbankschreibvorgang. Das mitgelieferte
  Skript kommt ohne MooTools aus, das Contao 5 im Backend nicht mehr ausliefert.
  Neu ist die Bedienung per Tastatur (Strg + Pfeiltaste).
* Change: Der tägliche Feed-Lauf hängt am Attribut `AsCronJob` statt an
  `$GLOBALS['TL_CRON']`, das es unter Contao 5 nicht mehr gibt. Die Feeds
  liegen jetzt unter `<Webverzeichnis>/share/`, wo auch Contao seine eigenen
  ablegt — bisher landeten sie im Wurzelverzeichnis.
* Change: Der Umschalter „veröffentlicht“ in der Albenliste nutzt den
  eingebauten Weg (`act=toggle&field=published`); der eigene Rückruf entfällt.
* Change: Die Ergänzungen an `tl_layout`, `tl_user` und `tl_user_group` laufen
  über den `PaletteManipulator` statt über `str_replace` auf die Kernpalette.
  Das frühere Ankerfeld `loadingOrder` gibt es unter Contao 5 nicht mehr.
* Fix: Das leere Ein-Punkt-Bild der versteckten Lightbox-Verweise steckt als
  Datenadresse im Markup. Bisher zeigte es auf
  `system/modules/photoalbums2/assets/blank.gif` — einen Pfad, den es seit
  Contao 4 nicht mehr gibt.
* Fix: Der Rückruf für den Zeitfilter schrieb auch beim Inhaltselement nach
  `tl_module`; jetzt wird die Tabelle aus dem Data Container genommen.
* Fix: `Pa2AlbumSorter` griff beim Sortieren nach Start- oder Enddatum auf eine
  nicht gesetzte Variable zu, wenn ein Album kein Datum hatte.
* Fix: Der Verweis „Album bearbeiten“ am Inhaltselement zeigte auf
  `contao/main.php`, das es seit Contao 4 nicht mehr gibt; die Adresse kommt
  jetzt aus dem Router.
