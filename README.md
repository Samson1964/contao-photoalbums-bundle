# Fotoalben für Contao

Verwaltet Fotoalben in Archiven und gibt sie im Frontend aus — als
Alben-Übersicht, als Foto-Ansicht eines einzelnen Albums oder als
Inhaltselement mitten im Artikel.

Das Bundle ist der Nachfolger von
[photoalbums2](https://github.com/Samson1964/contao-photoalbums2) und läuft
unter **Contao 4.13 und Contao 5** mit PHP 7.4 bis 8.4.

## Was neu ist gegenüber photoalbums2

* **Keine Fremdabhängigkeiten mehr.** Die Sortier-Assistenten aus
  `craffft/contao-imagesortwizard` und `craffft/contao-sortwizard` stecken jetzt
  im Bundle. Die Mehrsprachigkeit über `craffft/contao-translation-fields`
  entfällt ersatzlos; die dort abgelegten Texte holt eine Migration in die
  Felder zurück.
* **Contao 5 und PHP 8.4.** Der ganze Altbestand ist auf Namensräume,
  Dienste und die heutigen Contao-Schnittstellen umgestellt.
* **Aufnahmedaten vor 1970** lassen sich eintragen und erscheinen im Frontend.
* **Videos im Album.** Neben Fotos dürfen auch Videodateien in einem Album
  liegen; sie bekommen eine Platzhalterkachel und werden beim Anklicken in
  einem mitgelieferten Überlagerer abgespielt.

## Installation

```bash
composer require schachbulle/contao-photoalbums-bundle
```

Anschließend im Contao Manager beziehungsweise über die Kommandozeile die
Datenbank aktualisieren:

```bash
vendor/bin/contao-console contao:migrate
```

Dabei laufen zwei Dinge: Das Datenbankschema wird angepasst, und die Migration
„Fotoalben: Texte aus tl_translation_fields in die Felder zurücknehmen” holt
die Texte aus der Übersetzungstabelle zurück in die Felder.

Beim Umstieg von photoalbums2: Die Tabelle `tl_translation_fields` erst
löschen, wenn die Migration durchgelaufen ist — Contao bietet das Löschen beim
Datenbankabgleich an, und vorher wären die Texte weg. Einzelheiten in
[docs/umstieg.md](docs/umstieg.md).

## Umstieg von photoalbums2

Tabellen und Felder heißen unverändert `tl_photoalbums2_archive` und
`tl_photoalbums2_album`, die Feldnamen in `tl_module`, `tl_content`,
`tl_layout`, `tl_user` und `tl_user_group` bleiben ebenfalls gleich. Ein
Datenumzug ist deshalb nicht nötig.

Vorgehen — die Reihenfolge ist wichtig:

```bash
composer remove schachbulle/contao-photoalbums2 --no-update
composer require schachbulle/contao-photoalbums-bundle
vendor/bin/contao-console contao:migrate
```

Beide Pakete können nicht nebeneinander liegen; sie bringen dieselben Tabellen,
Felder und Modulnamen mit. Wer das alte Paket stehen lässt, bekommt von Composer
ein `conflicts with schachbulle/contao-photoalbums2`. Im Contao Manager beides
in **einem** Durchgang übernehmen: altes Paket zum Entfernen markieren, neues
hinzufügen, dann erst „Änderungen übernehmen".

Zum Schluss ein eventuell liegengebliebenes Verzeichnis
`system/modules/photoalbums2` aus dem Projekt entfernen.

Die Tabelle `tl_translation_fields` bleibt unangetastet — andere Erweiterungen
können sie noch brauchen. Nach dem Umzug lässt sie sich gefahrlos löschen, wenn
sonst nichts darauf zugreift.

Ausführlich beschrieben ist der Umstieg in [docs/umstieg.md](docs/umstieg.md).

## Aufbau

| Bereich | Beschreibung |
| --- | --- |
| Backend-Modul „Fotoalben“ | Archive anlegen, darin Alben mit Fotos, Aufnahmedatum und Meta-Angaben |
| Frontend-Modul „Fotoalbum“ | Übersicht und Foto-Ansicht, je nach Modus auf einer oder auf zwei Seiten |
| Frontend-Modul „Fotoalben Liste“ | Nur die Übersicht |
| Frontend-Modul „Fotoalbum Leser“ | Nur die Foto-Ansicht |
| Inhaltselement „Fotoalbum“ | Ein fest gewähltes Album mitten im Artikel |

### Ansichtsmodi des Moduls „Fotoalbum“

* **Auf einer Seite** — Übersicht und Fotos wechseln sich auf derselben Seite ab.
* **Nur Album-Ansicht mit Lightbox** — es gibt gar keine Foto-Seite; ein Klick
  auf die Kachel öffnet die Lightbox mit allen Fotos des Albums.
* **Auf getrennten Seiten** — Übersicht und Foto-Ansicht liegen auf zwei Seiten;
  das Modul wird in beide eingebunden.

## Lightbox: eine Voraussetzung im Seitenlayout

Die Fotos und die Album-Kacheln werden mit `data-lightbox="pa2_…"` ausgezeichnet
— genau so, wie es auch die Bild-Elemente des Contao-Kerns tun. **Die Lightbox
selbst bringt das Bundle nicht mit**, denn welche zum Einsatz kommt, entscheidet
das Theme.

Ohne eine aktivierte Lightbox öffnet ein Klick das Foto schlicht im selben
Fenster. Wer Contaos eigene benutzen möchte, stellt sie im Seitenlayout ein:

* **Seitenlayout → jQuery** einschalten und dort das Template `j_colorbox`
  auswählen, **oder**
* **Seitenlayout → MooTools** einschalten und dort `moo_mediabox` auswählen.

Beide Wege gibt es unverändert in Contao 4.13 und Contao 5. Bringt das Theme
eine eigene Lightbox mit, muss diese lediglich auf `a[data-lightbox]` hören;
der Wert des Attributs ist je Album eindeutig und gruppiert die Fotos.

## Videos

Ein Album darf neben Fotos auch Videos enthalten. Ausgewählt werden sie im
selben Feld „Fotos und Videos“, sortiert werden sie im selben Assistenten.

Zugelassen sind ab Werk `mp4`, `m4v`, `webm` und `ogv` — alle vier dürfen mit
Contaos Voreinstellung für erlaubte Dateitypen ohne weitere Einrichtung
hochgeladen werden. Wer die Liste ändern möchte, überschreibt sie in der
eigenen `config.php`:

```php
$GLOBALS['pa2']['videoExtensions'] = 'mp4,webm';
$GLOBALS['pa2']['mediaExtensions'] = $GLOBALS['pa2']['imageExtensions'].','.$GLOBALS['pa2']['videoExtensions'];
```

Ein Video geht **nicht** durch die Bildbearbeitung — das Bundle greift keine
Einzelbilder aus der Datei. Stattdessen zeigt die Kachel eine einheitliche
Platzhaltergrafik in der Größe, die für die Ansicht eingestellt ist.

### Warum ein eigener Überlagerer und nicht die Lightbox

Die Lightbox des Themes bekommt ihre Verweise über `data-lightbox` und ihre
Einstellungen **einmal für alle**. colorbox etwa erkennt am Dateinamen nur
Bilder und versuchte, ein Video als Bild zu laden. Ein Video trägt deshalb
bewusst **kein** `data-lightbox`, sondern `data-pa2-video`; darum kümmert sich
`photoalbums-video.js`.

Das Skript kommt ohne Bibliothek aus und läuft damit unabhängig davon, ob das
Theme jQuery, MooTools oder gar nichts einbindet. Geladen wird es — samt der
zugehörigen Stilvorlage — nur auf Seiten, auf denen tatsächlich ein Video
steht. Anklicken öffnet, **Escape** oder ein Klick auf den Hintergrund
schließt; beim Schließen hält das Video an und springt an den Anfang.

Wer den Überlagerer anders gestalten möchte, überschreibt die Regeln im
eigenen Stylesheet — alle Klassen beginnen mit `pa2-videobox`. Ist JavaScript
abgeschaltet, führt der Verweis auf die Videodatei selbst.

### Was ein Video nicht sein kann

* **Kein Vorschaubild von Hand.** Das Feld „Vorschau Foto auswählen“ zeigt nur
  Fotos an. Automatisch gewählt (erstes oder zufälliges Foto) kommt ein Video
  erst dann zum Zuge, wenn das Album **überhaupt kein** Foto enthält — dann
  steht die Platzhalterkachel als Aufmacher.
* **Kein Teil der Lightbox-Gruppe.** In der Foto-Ansicht bleibt die Gruppe des
  Themes den Fotos vorbehalten; ein Video auf einer anderen Seite der
  Blätterliste taucht dort gar nicht erst als versteckter Verweis auf.
* **Nicht mitgezählt als Video.** Die Meta-Angabe „Fotoanzahl“ zählt alle
  Einträge eines Albums, nennt sie aber weiterhin Fotos.

## Templates

| Template | Zweck |
| --- | --- |
| `pa2_wrap` | Rahmen um beide Ansichten |
| `pa2_album` | Kachel eines Albums, in Zeilen |
| `pa2_album_fluid` | Kachel eines Albums, als Liste |
| `pa2_image` | Kachel eines Fotos, in Zeilen |
| `pa2_image_fluid` | Kachel eines Fotos, als Liste |
| `pa2_lightbox_image` | Versteckter Verweis für die Lightbox |
| `pa2_empty` | Meldung, wenn nichts auszugeben ist |

Das mitgelieferte Stylesheet lässt sich im Seitenlayout unter „Fotoalben
Stylesheet ignorieren” abschalten.

Es hält sich streng an das eigene Markup: Jede Regel steht innerhalb der
Umhüllungen `.albumswrap` beziehungsweise `.imagewrap`, die nur die
mitgelieferten Templates setzen. Wer eigene Templates benutzt — etwa mit einem
Bootstrap-Raster —, bekommt von dort nichts ab und braucht den Schalter im
Seitenlayout gar nicht erst.

## Sortier-Assistenten

Fotos und Alben lassen sich von Hand in eine eigene Reihenfolge bringen. Dazu
im Album unter „Fotos sortieren“ beziehungsweise im Modul unter „Alben
sortieren“ den Eintrag **Eigene Sortierung** wählen; darunter erscheint dann
der Assistent.

Die Reihenfolge wird mit der Maus gezogen. Wer die Tastatur bevorzugt: Eintrag
anklicken und mit **Strg + Pfeiltaste** verschieben. Gespeichert wird erst beim
Absenden des Formulars.

## RSS- und Atom-Feeds

Je Archiv lässt sich ein Feed erzeugen. Die Datei landet im Verzeichnis
`share/` unterhalb des Webverzeichnisses. Erzeugt wird sie täglich über den
Contao-Cron und außerdem beim nächsten Aufruf des Backend-Moduls, nachdem ein
Album oder ein Archiv geändert wurde. Geschützte Archive bekommen keinen Feed —
er wäre öffentlich lesbar.

## Kommentare

Kommentare zu einem Album setzen das Paket `contao/comments-bundle` voraus.
Fehlt es, bleibt der Bereich einfach leer.

## Prüfstand

`tools/pruefstand.php` prüft ohne Datenbank, ob sich Klassen, Konfiguration,
Sprachdateien und Datenbereiche unter einer bestimmten Contao-Fassung laden
lassen:

```bash
php tools/pruefstand.php /pfad/zur/contao-installation
```

## Lizenz

LGPL-3.0-or-later. Die Urfassung stammt von Daniel Kiesel (craffft.de).
