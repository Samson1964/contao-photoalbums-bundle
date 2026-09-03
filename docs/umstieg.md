# Umstieg von photoalbums2 auf das Fotoalben-Bundle

Diese Anleitung beschreibt den Wechsel von
`schachbulle/contao-photoalbums2` (beziehungsweise
`craffft/contao-photoalbums2`) auf `schachbulle/contao-photoalbums-bundle`.

## Was gleich bleibt

* Die Tabellen heißen weiterhin `tl_photoalbums2_archive` und
  `tl_photoalbums2_album`.
* Die Feldnamen in diesen Tabellen bleiben unverändert.
* Die Ergänzungen an `tl_module`, `tl_content`, `tl_layout`, `tl_user` und
  `tl_user_group` heißen weiterhin `pa2…` beziehungsweise `photoalbums2s` und
  `photoalbums2p`.
* Die Templates heißen weiterhin `pa2_wrap`, `pa2_album`, `pa2_album_fluid`,
  `pa2_image`, `pa2_image_fluid`, `pa2_lightbox_image` und `pa2_empty`.
* Die Adressen der Foto-Ansicht ändern sich nicht.

Ein Datenumzug ist deshalb nicht nötig. Bestehende Module, Inhaltselemente und
Berechtigungen arbeiten unverändert weiter.

## Was sich ändert

### Die Mehrsprachigkeit entfällt

photoalbums2 hat fünf Felder über die Erweiterung
`craffft/contao-translation-fields` mehrsprachig geführt:

| Tabelle | Feld |
| --- | --- |
| `tl_photoalbums2_album` | `event` |
| `tl_photoalbums2_album` | `place` |
| `tl_photoalbums2_album` | `photographer` |
| `tl_photoalbums2_album` | `description` |
| `tl_content` | `pa2Teaser` |
| `tl_module` | `pa2Teaser` |

In diesen Feldern stand nicht der Text, sondern eine Nummer, die auf eine Zeile
in `tl_translation_fields` verwies. Genau daher rührt der Fehler, dass im
Backend und im Frontend eine nackte Zahl statt des Textes erscheint.

Die mitgelieferte Migration behandelt jede reine Zahl in diesen Feldern nach
drei Regeln:

1. **Zu der Nummer gibt es eine Zeile mit Text** — der Text tritt an die Stelle
   der Nummer. Bevorzugt wird die deutsche Fassung; gibt es sie nicht, wird die
   erste vorhandene genommen.
2. **Die Zeile gibt es, sie ist aber leer** — dann war das Feld auch unter
   photoalbums2 leer, und die Nummer ist nichts als ein Überbleibsel des
   Übersetzungsverfahrens. Sie kommt weg, das Feld bleibt leer.
3. **Zu der Nummer gibt es gar keine Zeile** — dann ist die Zahl kein Verweis,
   sondern ein echter Wert (ein Ereignis „1968" etwa). Sie bleibt unangetastet.

Enthält ein Feld einen Text statt einer Nummer, bleibt er ohnehin unberührt.

Die Tabelle `tl_translation_fields` wird **nicht** verändert und **nicht**
gelöscht. Wenn keine andere Erweiterung mehr darauf zugreift, kann sie nach dem
Umzug von Hand entfernt werden.

### Das Feld `tl_content.pa2Teaser` wechselt den Typ

Es war eine Ganzzahlspalte (die Verweisnummer) und wird zu einer Textspalte.
Contao führt Migrationen einmal vor und einmal nach dem Angleichen des
Schemas aus; die Migration erkennt das und wird erst im zweiten Durchgang
tätig, wenn die Spalte den Text auch aufnehmen kann.

### Start- und Enddatum werden breiter

`tl_photoalbums2_album.startdate` und `.enddate` wachsen von `varchar(10)` auf
`varchar(11)`. Ein Unix-Zeitstempel vor 1970 ist negativ und braucht die
zusätzliche Stelle für das Minuszeichen.

### Die Feeds ziehen um

Die XML-Dateien lagen bisher im Wurzelverzeichnis der Installation. Sie liegen
jetzt unter `<Webverzeichnis>/share/`, also dort, wo Contao auch seine eigenen
Feeds ablegt. Alte Dateien im Wurzelverzeichnis bleiben liegen und können von
Hand gelöscht werden.

## Schritt für Schritt

1. **Sicherung.** Datenbank und Dateien sichern — die Migration schreibt in
   sechs Feldern über alle Datensätze hinweg.

2. **Altes Paket aus der `composer.json` nehmen — vor dem Einbau des neuen.**

   ```bash
   composer remove schachbulle/contao-photoalbums2 --no-update
   ```

   Das `--no-update` sorgt dafür, dass nur die `composer.json` geändert wird;
   aufgelöst wird erst im nächsten Schritt, dann in einem Rutsch.

   Wer den Contao Manager benutzt, markiert das alte Paket zum Entfernen und
   fügt das neue hinzu, **bevor** er „Änderungen übernehmen" drückt — der
   Manager wendet beides gemeinsam an.

3. **Neues Bundle installieren.**

   ```bash
   composer require schachbulle/contao-photoalbums-bundle
   ```

### Wenn Composer sich weigert

Bleibt das alte Paket in der `composer.json` stehen, bricht Composer ab:

```text
- schachbulle/contao-photoalbums-bundle dev-master conflicts with
  schachbulle/contao-photoalbums2 1.2.0.
```

Das ist Absicht: Beide Pakete bringen dieselben Tabellen, Felder, Templates und
Frontend-Modul-Namen mit und würden sich gegenseitig überschreiben. Die Abhilfe
ist immer Schritt 2 — das alte Paket zuerst herausnehmen.

Ältere Fassungen des neuen Bundles meldeten an dieser Stelle
`They all replace contao-legacy/photoalbums2`. Das zeigte auf ein Paket aus
Contao-3-Zeiten und nicht auf das, was wirklich im Weg stand; seit 1.0.0 nennt
die Meldung das richtige Paket.

4. **Datenbank aktualisieren.**

   ```bash
   vendor/bin/contao-console contao:migrate
   ```

   In der Ausgabe muss die Migration
   „Fotoalben: Texte aus tl_translation_fields in die Felder zurücknehmen“
   auftauchen. Sie meldet zum Schluss, wie viele Datensätze je Tabelle und Feld
   geändert wurden.

5. **Reste aufräumen.** Falls aus Contao-3-Zeiten noch ein Verzeichnis
   `system/modules/photoalbums2`, `system/modules/imagesortwizard`,
   `system/modules/sortwizard` oder `system/modules/translation-fields` im
   Projekt liegt: entfernen. Contao 4 und 5 lesen es nicht mehr, es stiftet aber
   Verwirrung.

6. **Cache leeren.**

   ```bash
   vendor/bin/contao-console cache:clear
   ```

7. **Nachsehen.** Im Backend ein Album öffnen: Ereignis, Aufnahmeort, Fotograf
   und Beschreibung müssen Text zeigen, keine Zahlen. Im Frontend eine
   Alben-Übersicht und eine Foto-Ansicht aufrufen.

## Wenn nach dem Umzug noch Zahlen erscheinen

Nach den Regeln oben bleibt eine Zahl nur noch in zwei Fällen stehen:

1. **Es gibt gar keine Zeile zu der Nummer.** Dann ist die Zahl vermutlich echt
   — ein Ereignis „1968" etwa — und wird gar nicht erst als Verweis behandelt.
2. **Der hinterlegte Text besteht selbst nur aus Ziffern und ließe sich wieder
   als Verweis lesen.** Solche Ketten rührt die Migration nicht an; sie nennt
   ihre Anzahl am Ende der Meldung.

Prüfen lässt sich der Bestand so:

```sql
SELECT id, event, place, photographer
FROM tl_photoalbums2_album
WHERE event REGEXP '^[0-9]+$'
   OR place REGEXP '^[0-9]+$'
   OR photographer REGEXP '^[0-9]+$';
```

Liefert die Abfrage Zeilen, lässt sich zu jeder Nummer nachsehen, ob es sie in
der Übersetzungstabelle noch gibt:

```sql
SELECT * FROM tl_translation_fields WHERE fid = <Nummer>;
```

Kommt dort nichts zurück, ist die Zahl kein Verweis, sondern ein echter Wert —
sie gehört genau so ins Feld. Kommt eine Zeile zurück, deren Inhalt selbst nur
aus Ziffern besteht, liegt eine Kette vor; dann hilft nur, den Text von Hand
nachzutragen.

## Daten vor 1970

Aufnahmedaten vor dem 1. Januar 1970 lassen sich jetzt im Backend eintragen und
erscheinen im Frontend. Wer solche Daten früher direkt in der Datenbank
eingetragen hat, muss nichts weiter tun — sie werden ab sofort ausgegeben.

Ein Sonderfall bleibt: Der Wert `0` gilt weiterhin als „kein Datum“. Der
1. Januar 1970, 00:00 Uhr lässt sich als Aufnahmedatum also nicht abbilden.
