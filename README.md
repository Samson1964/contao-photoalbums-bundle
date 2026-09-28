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

## Alias eines Albums

Der Alias ist Teil der Adresse und besteht nur aus Kleinbuchstaben, Ziffern
und Bindestrichen. Umlaute werden umgeschrieben — `ä` zu `ae`, `ß` zu `ss`,
Akzente fallen weg (`Café` → `cafe`, `Čačak` → `cacak`). Das gilt für den
automatisch aus dem Titel gebildeten Alias ebenso wie für eine eigene
Eingabe. Beginnt er mit einer Ziffer, wird `id-` vorangestellt. So hießen die
Alben schon unter photoalbums2 und Contao 3:
`laenderkampf-oesterreich-bayern-2005`.

Unter Contao 4 und 5 ließ `StringUtil::standardize()` Umlaute stehen, und es
entstanden Aliase wie `dsam-düsseldorf-2023`. Beim Aktualisieren bietet der
Installationsassistent deshalb die Migration **„Fotoalben: Umlaute in
Album-Aliassen umschreiben“** an. Sie fasst ausschließlich Aliase mit
Sonderzeichen an; alle anderen bleiben unverändert, auch wenn die Regel sie
heute etwas anders bilden würde. Stößt ein umgeschriebener Alias auf einen
vorhandenen, bekommt er die Albumnummer angehängt. Das Protokoll der Migration
nennt jede Änderung als „alt → neu“.

**Alte Adressen funktionieren weiter.** Wer `…/dsam-düsseldorf-2023` aufruft
— über ein Lesezeichen, eine Suchmaschine oder einen Verweis von außen —,
wird mit **301** auf `…/dsam-duesseldorf-2023` weitergeleitet; angehängte
Parameter wie `?page=2` gehen mit. Suchmaschinen übernehmen damit die neue
Adresse. Auch handgeschriebene Insert-Tags mit altem Alias finden das Album.
Picker-Verweise sind ohnehin nicht betroffen: Sie tragen die Nummer.

## Auf ein Album verlinken

Im Link-Picker des Backends — dort, wo auch Seiten, Dateien, Nachrichten,
Events, FAQ und Artikel stehen — gibt es einen Reiter **Fotoalben**. Er öffnet
die Albenliste; ein Klick auf ein Album fügt `{{photoalbum_url::5}}` ein. Ist
schon ein Album verlinkt, öffnet der Picker gleich dessen Archiv mit dem Album
vorausgewählt. Der Reiter erscheint nur für Benutzer, die das Backend-Modul
„Fotoalben“ öffnen dürfen.

Dieselben Tags lassen sich auch von Hand schreiben:

| Tag | Ergebnis |
| --- | --- |
| `{{photoalbum_url::5}}` | Adresse des Albums |
| `{{photoalbum::5}}` | Vollständiger Verweis mit dem Titel |
| `{{photoalbum_open::5}}` | Nur das öffnende `<a>` |
| `{{photoalbum_title::5}}` | Nur der Titel |

Statt der Nummer darf auch der Alias stehen. Der Picker trägt bewusst die
**Nummer** ein: Ändert sich der Alias später, bleibt der Verweis gültig. Mit
`|absolute` — etwa `{{photoalbum_url::5|absolute}}` — entsteht eine Adresse
mit Schema und Domain, wie sie ein Newsletter braucht.

### Unter welcher Adresse ist ein Album zu sehen?

Anders als Nachrichten haben Fotoalben keine feste Weiterleitungsseite: Wo ein
Album erscheint, ergibt sich aus der Einbindung der Module. Das Bundle sucht
diese Stellen zusammen und nimmt die erste veröffentlichte Seite in dieser
Rangfolge:

1. Die Seite, die im Archiv unter **„Seite mit der Foto-Ansicht“** eingetragen
   ist. Sie übersteuert alles Folgende.
2. Die Detailseite eines Moduls „Fotoalbum“ im Modus „auf getrennten Seiten“
   oder eines Moduls „Fotoalben Liste“.
3. Die Seite, auf der ein Modul „Fotoalbum Leser“ eingebunden ist.
4. Die Seite, auf der ein Modul „Fotoalbum“ im Modus „auf einer Seite“ steht.
5. Die Seite eines Inhaltselements „Fotoalbum“, das genau dieses Album zeigt.
6. Die Seite eines Moduls im Modus „Nur Album-Ansicht mit Lightbox“.

Module, deren Archiv-Auswahl das Archiv des Albums enthält, gehen dabei stets
vor den übrigen. Bei mehrsprachigen Auftritten gewinnt bei Gleichstand die
Seite aus demselben Seitenbaum — ein Verweis auf der englischen Seite führt auf
die englische Galerie.

**Welche Seite herauskommt, zeigt die Albenliste im Backend** unter jedem
Album: „Verweise führen auf: …“ samt dem Grund. Findet sich keine Seite, steht
dort ein Hinweis — noch bevor jemand im Picker ein Album wählt, das ins Leere
führen würde.

Nicht berücksichtigt werden Module, die über das Seitenlayout oder
`{{insert_module::…}}` eingebunden sind: Sie hängen an keiner bestimmten
Seite. Für diesen Fall ist die Einstellung am Archiv da.

Dieselbe Ermittlung bildet auch die Verweise im RSS-/Atom-Feed. Die Seite am
Archiv ist deshalb keine Pflichtangabe des Feeds mehr.

## Album herunterladen

Ein Album lässt sich als ZIP-Archiv herunterladen — mit allen Fotos und Videos
in Originalgröße und einer Textdatei `album.txt`, die Titel, Aufnahmedatum,
Ereignis, Ort, Fotograf und Beschreibung festhält. Alles liegt im Archiv in
einem Ordner, der nach dem Alias des Albums heißt, damit beim Entpacken kein
Dateisalat entsteht.

Angeboten wird der Download über zwei Schalter in den Moduleinstellungen:

| Schalter | Wirkung |
| --- | --- |
| Download in Alben-Übersicht anbieten | Ein Knopf auf jeder Album-Kachel |
| Download in Foto-Ansicht anbieten | Ein Knopf über den Fotos des geöffneten Albums |

Beide sind ab Werk **aus**. Steht ein Schalter aus, liefert auch die Adresse
mit `pa2_download` nichts — ein abgeschalteter Knopf ist also wirklich aus und
nicht bloß unsichtbar.

**Wer ein Album sehen darf, darf es herunterladen.** Geprüft wird mit derselben
Klasse wie bei der Anzeige: veröffentlicht, Archiv nicht gesperrt, Schutz des
Albums beachtet, Zeitfilter des Moduls eingehalten. Es gibt bewusst keine
zweite Zugriffsregel, die von der Anzeige abweichen könnte. Ein Album, das der
Besucher nicht sehen darf, liefert nicht etwa einen Fehler, sondern die gewohnte
Seite — das verrät nicht einmal, ob es das Album überhaupt gibt.

Der Knopf trägt `rel="nofollow"` und steht zwischen `indexer::stop` und
`indexer::continue`; weder Suchmaschinen noch Contaos eigener Indexer fordern
das Archiv also an. Die Antwort trägt zusätzlich `X-Robots-Tag: noindex,
nofollow`.

### Große Alben

Alben mit weit über tausend Fotos sind ausdrücklich vorgesehen. Der übliche Weg
— `ZipArchive` eine Datei bauen lassen und sie danach ausliefern — scheitert
daran gleich dreifach: Er braucht den Platz noch einmal auf der Platte, der
Besucher wartet ohne jedes Lebenszeichen, bis das Archiv fertig ist, und
vorher läuft die Laufzeitbegrenzung ab.

Das Bundle schreibt das Archiv deshalb **unmittelbar in die Ausgabe**. Der
Download beginnt sofort, es wird nichts zwischengespeichert, und der
Speicherbedarf bleibt bei 256 KiB — gleichgültig, ob das Album zehn Fotos
enthält oder zehntausend. Möglich machen das drei Entscheidungen im Format:

* **Keine Komprimierung.** Fotos und Videos sind bereits komprimiert; sie noch
  einmal durch Deflate zu schicken kostet viel Rechenzeit und spart nichts.
* **Nachgestellte Prüfsumme** (Data Descriptor). Die CRC-32 einer Datei steht
  erst fest, wenn sie ganz gelesen ist — ohne diesen Kunstgriff müsste jede
  Datei zweimal gelesen werden.
* **ZIP64 durchgehend.** Ein klassisches ZIP endet bei 4 GB und 65535
  Einträgen. Die Grenze nur manchmal zu überschreiten wäre die schlechtere
  Wahl: Dann führe der Weg für große Archive durch Code, den nie jemand
  ausprobiert hat.

Weil ohne Komprimierung jede Länge von vornherein feststeht, kann das Bundle
die Größe des fertigen Archivs **vorab ausrechnen** und als `Content-Length`
mitgeben. Der Browser zeigt damit Fortschritt und Restdauer an — bei einem
Album von mehreren Gigabyte der Unterschied zwischen „lädt“ und „hängt“.

Zwei Dinge, die ein Server dafür mitbringen muss:

* Ein **Zeitlimit für die Übertragung** darf es nicht geben. Das Bundle setzt
  `set_time_limit(0)`; wo ein Hoster die Laufzeit hart begrenzt, bricht ein
  sehr großes Archiv trotzdem ab.
* `display_errors` muss **aus** sein. Eine einzige PHP-Warnung stünde mitten
  im Archiv und machte es unlesbar. Im `prod`-Modus stellt Contao das von
  selbst sicher.

Die Sitzung wird vor dem Streamen geschlossen; der Besucher kann also
weitersurfen, während sein Album lädt.

### Nachweis

`tools/zipprobe.php` prüft den ZIP-Schreiber ohne Contao und ohne Datenbank:

```bash
php tools/zipprobe.php
```

Jeder Prüffall wird in einem Unterprozess erzeugt — also über denselben Weg wie
im Webserver — und danach mit PHPs `ZipArchive` gegengelesen. Geprüft werden
die byte-genaue Vorausberechnung, Dateien über mehrere Blöcke, gleiche
Dateinamen aus verschiedenen Ordnern, Umlaute im Dateinamen, Änderungszeiten
vor 1980, **1200 Dateien in einem Archiv** und der Fall, dass eine Datei
zwischen Anmeldung und Ausgabe schrumpft.

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
