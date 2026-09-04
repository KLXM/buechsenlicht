# Büchsenlicht-Kalender

Ein REDAXO-AddOn für Jägerinnen und Jäger: aus einem eingegebenen Ort, den landesspezifischen
Jagdzeiten und einer gewählten Wildart wird ein persönlicher ICS-Kalender mit den morgendlichen und
abendlichen Büchsenlichtzeiten erzeugt (Standard: 90 Minuten vor Sonnenaufgang bzw. nach
Sonnenuntergang, frei einstellbar). Der Kalender kann heruntergeladen oder als `webcal://`-Feed
abonniert werden und bleibt dann dauerhaft aktuell. Zeitraum: heute bis heute + 5 Jahre. Zeitzone:
`Europe/Berlin`.

> **Planungswerkzeug – keine amtliche Rechtsauskunft.** Aktuelle Rechtslage und örtliche
> Anordnungen sind vor der Jagd eigenständig zu prüfen. Für einzelne Bundesländer stammen die
> hinterlegten Jagdzeiten aus einer benannten Zweitquelle statt dem amtlichen Verordnungstext
> selbst – siehe [Datenquellen](#jagdzeiten-daten--quellen) und den „Datenquellen“-Link in der App.

## Inhalt

- [Funktionsumfang](#funktionsumfang)
- [Voraussetzungen](#voraussetzungen)
- [Installation](#installation)
- [Einbindung](#einbindung)
- [Kalender abonnieren](#kalender-abonnieren)
- [Jagdzeiten-Daten & Quellen](#jagdzeiten-daten--quellen)
- [Architektur](#architektur)
- [Bekannte Einschränkungen](#bekannte-einschränkungen)
- [Lizenz](#lizenz)

## Funktionsumfang

- **Ortssuche mit Live-Vorschlägen** während der Eingabe (Autovervollständigung), plus expliziter
  „Ort bestimmen“-Button als Fallback. Läuft über einen serverseitigen Proxy, nicht direkt aus dem
  Browser gegen den externen Geodienst.
- **Automatische Bundesland-Erkennung** aus dem gewählten Ort – keine manuelle Auswahl nötig.
- **Wildart-Auswahl** inkl. „Rehwild · alle Klassen“, das rechnerisch als echte Schnittmenge aus
  Böcken, Schmalrehen, Ricken und Kitzen ermittelt wird (nicht als größtmöglicher Zeitraum).
- **Sofort aktualisierte Info-Box** mit Bundesland, Wildart, Jagdzeit, Rechtsgrundlage samt Link
  und Datenstand.
- **Büchsenlicht-Berechnung** über den NOAA-Sonnenalgorithmus, inkl. korrekter Sommer-/Winterzeit.
- **ICS-Download** direkt im Browser erzeugt, keine Serveranfrage nötig.
- **Abonnierbarer ICS-Feed** (`webcal://`, Google Kalender, Outlook/Microsoft 365, plus Klartext-
  Link mit Kopieren-Button für alle übrigen Kalenderprogramme) – aktualisiert sich selbstständig.
- **„Einzelnen Tag prüfen“-Modal**: beliebiges Datum (innerhalb des 5-Jahres-Zeitraums) wählen und
  sofort sehen, ob es ein Jagdtag ist, inkl. Sonnenauf-/-untergang und Büchsenlicht-Fenstern - ohne
  den ganzen Kalender zu erzeugen.
- **„Datenquellen“-Modal**: Übersicht aller 16 Bundesländer mit Quelle, Link und Datenstand.
- Durchgängig **UIkit 3**, responsive für Desktop, Tablet und Smartphone.

## Voraussetzungen

- REDAXO `^5.15`
- PHP `>=8.1`
- **UIkit 3 muss auf der Website bereits eingebunden sein** (z. B. über das AddOn
  `uikit_theme_builder` oder das Template selbst) – dieses AddOn lädt UIkit 3 bewusst nicht selbst
  nach (auch nicht per CDN-Fallback), um doppeltes Laden bzw. Versionskonflikte zu vermeiden
- Ausgehende HTTPS-Verbindungen vom Webserver aus erlaubt (für die Ortssuche über Photon und den
  ICS-Feed)

## Installation

Wie jedes REDAXO-AddOn: in `src/addons/` ablegen und über die AddOn-Verwaltung installieren und
aktivieren, oder per Konsole:

```bash
php bin/console package:install buechsenlicht
php bin/console package:activate buechsenlicht
```

Bei der Installation wird automatisch das Modul **„Büchsenlicht-Kalender“** angelegt (Key
`buechsenlicht_kalender`). Das passiert idempotent über `lib/Installer.php::syncModule()`, das
sowohl aus `install.php` (Erstinstallation) als auch aus `update.php` (Versions-Update) aufgerufen
wird – Änderungen an `module/module_output.inc` bzw. `module/module_input.inc` kommen also auch
bei einem reinen Versions-Update bei bestehenden Installationen an, nicht nur bei einer
Neuinstallation.

Nach Änderungen an `assets/*.js`/`*.css` müssen die Assets veröffentlicht werden:

```bash
php bin/console assets:sync
```

## Einbindung

### Im Frontend (Modul)

Das Modul **„Büchsenlicht-Kalender“** in einem Artikel als Block einfügen. Es hat **keine
Eingabefelder** – Ort, Wildart und Kalenderoptionen wählt der Besucher direkt auf der Seite. Die
komplette Oberfläche (drei Cards: Ort/Revier, Wildart, Kalender) wird ausgegeben.

### Im Backend (Testseite)

Unter **AddOns → Büchsenlicht-Kalender** steht eine Testseite bereit, die exakt denselben Baustein
rendert wie das Frontend-Modul (`lib/Widget.php` wird von beiden aufgerufen) – ideal, um Änderungen
zu prüfen, ohne einen Artikel anzulegen.

### Direkt per PHP

```php
echo \Buechsenlicht\Widget::render();
```

## Kalender abonnieren

Nach „Kalender erstellen“ zeigt die App unter „Kalender abonnieren“ vier Wege, je nachdem was der
Kalender des Nutzers unterstützt:

1. **`webcal://`-Link** – von den meisten Desktop-/Mobil-Kalender-Apps (Apple Kalender, viele
   Android-Apps u. a.) direkt als Abo erkannt.
2. **„Google Kalender“-Button** – öffnet Google Calendars dokumentierten
   `calendar/render?cid=`-Deeplink mit vorausgefüllter Feed-URL, Nutzer bestätigt nur noch.
3. **„Outlook / Microsoft 365“-Button** – analog über Outlooks `addfromweb?url=`-Deeplink.
4. **Klartext-Link zum Kopieren** (mit eigenem „Kopieren“-Button, Zwischenablage-API mit
   Fallback) – für alle Kalenderprogramme, die weder `webcal://` noch die beiden Deeplinks
   unterstützen; einfach unter „Kalender abonnieren/von URL/aus dem Internet“ einfügen.

Kalender-Apps rufen den Link periodisch erneut ab; da der Zeitraum immer bei „heute“ beginnt, bleibt
das Abo dauerhaft aktuell, ohne dass der Nutzer die Datei erneut herunterladen muss. Technisch ist
das ein öffentlicher, zustandsloser API-Endpunkt (`rex-api-call=buechsenlicht_feed`, kein Login
nötig) – alle nötigen Parameter (Ort, Koordinaten, Bundesland, Wildart, Büchsenlicht-Werte) stecken
in der URL selbst. Das ist zugleich der wichtigste Datenschutz-Punkt: Diese URL enthält Ort und
Wildart offen lesbar und liegt nach dem Abonnieren in der Kontrolle der Kalender-App bzw. deren
Cloud-Anbieter – erklärt der App selbst im Bereich „Rechtliches & Datenschutz“ (siehe unten).

> **Zum Testen:** Google Kalender und Outlook rufen den Feed von ihren eigenen Servern ab, nicht
> vom Gerät des Nutzers. Das funktioniert nur mit einer **öffentlich erreichbaren** Domain und
> einem gültigen (nicht selbstsignierten) HTTPS-Zertifikat – eine lokale `.local`-Testinstanz
> (mDNS, nur im eigenen Netz auflösbar) kann von diesen Diensten grundsätzlich nicht erreicht
> werden, das Abo bleibt dort leer. Kein Code-Fehler, sondern eine Eigenschaft solcher
> Testumgebungen.

## Rechtliches & Datenschutz

Am unteren Rand der App gibt es einen ausklappbaren Bereich „Rechtliches & Datenschutz“ mit:
Kostenfreiheit/Freibleiben des Dienstes, Haftungsausschluss, dem Datenstand der Jagdzeiten-Recherche
(`HuntingSeasons::RESEARCHED_AT`, getrennt vom `datenstand` je Bundesland, der die Version der
jeweiligen Verordnung angibt) sowie separaten Datenschutzhinweisen zu Ortssuche und Kalender-Abo.
Dieser Text ist als vernünftiger Standard formuliert, aber **keine Rechtsberatung** – bei
produktivem Einsatz auf einer öffentlichen Website empfiehlt sich eine kurze Prüfung durch
eine:n Jurist:in, insbesondere im Zusammenspiel mit der ohnehin vorhandenen
Datenschutzerklärung/Impressum der Website.

## Jagdzeiten-Daten & Quellen

Die Jagdzeiten-Datenbasis für alle 16 Bundesländer liegt in `lib/HuntingSeasons.php::data()` und
wurde gegen die jeweils aktuelle Landesverordnung recherchiert (Stand: 2026-09-04). Innerhalb der
App zeigt der **„Datenquellen“-Link** im oberen Banner ein Modal mit Quelle, Link und Datenstand je
Bundesland.

Drei Punkte sind dabei wichtig und werden transparent ausgewiesen:

1. **Bundesrecht als Rückfall ist normal, keine Datenlücke**: Regelt ein Bundesland eine Wildart
   nicht in seiner eigenen Verordnung (z. B. Muffelwild in Bayern, das in § 19 AVBayJG schlicht
   nicht auftaucht), gilt automatisch die Bundesjagdzeitenverordnung (BJagdZV) weiter –
   Landesrecht kann Bundesrecht nur ergänzen/verschärfen, nicht durch Schweigen aufheben. Diese
   Fälle sind an der Quelle „Bundesjagdzeitenverordnung“ in Tabelle/Modal erkennbar und im
   `hinweis`-Feld des jeweiligen Bundeslands benannt – es ist geltendes Recht, kein fehlender Wert.
2. **Vereinfachte Klassen**: Wo eine Landesverordnung eine Wildart in mehr Alters-/Geschlechts-
   klassen aufteilt als diese App abbildet (z. B. Rotwild-Schmaltiere mit abweichenden, meist
   früheren Zeiten), wird die Hauptklasse verwendet („Hirsche und Alttiere“ o. Ä.) – vermerkt im
   `hinweis`-Feld des jeweiligen Bundeslands.
3. **Zweitquellen bei 5 Ländern**: Die amtlichen Rechtsportale von Berlin, Mecklenburg-Vorpommern,
   Rheinland-Pfalz, Saarland und Schleswig-Holstein sind JavaScript-Anwendungen, die sich nicht
   automatisiert auslesen lassen. Dort stammen die Werte aus einer benannten, amtlich anerkannten
   Zweitquelle (z. B. Landesjagdverband-Übersicht) statt dem Verordnungstext selbst – im `hinweis`
   mit „⚠“ gekennzeichnet und in `HuntingSeasons::THIRD_PARTY_SOURCES` benannt. Anders als Punkt 1
   ist das eine echte Unsicherheit (Quelle ist amtlich anerkannt, aber nicht der Originaltext).

**Wartung**: Perioden werden als `["MM-DD", "MM-DD"]` hinterlegt, ein Jahreswechsel wird über
Start > Ende codiert (z. B. `["09-01", "01-31"]` = 1. September bis 31. Januar). Änderungen
erfolgen zentral in `lib/HuntingSeasons.php::data()`; „Rehwild · alle Klassen“ wird daraus zur
Laufzeit als Schnittmenge berechnet, nicht separat gepflegt.

## Architektur

| Datei | Zweck |
|---|---|
| `lib/HuntingSeasons.php` | Jagdzeiten-Datenbasis + Schnittmengen-Logik für „Rehwild · alle Klassen“ |
| `lib/SunCalc.php` | Sonnenauf-/-untergang nach dem NOAA-Sonnenalgorithmus (PHP) |
| `lib/CalendarGenerator.php` | Erzeugt die Terminliste für einen Zeitraum |
| `lib/IcsBuilder.php` | Baut die `.ics`-Datei inkl. `VTIMEZONE Europe/Berlin` |
| `lib/Api/FeedApi.php` | Öffentlicher, abonnierbarer ICS-Feed (`buechsenlicht_feed`) |
| `lib/Api/GeocodeApi.php` | Serverseitiger Proxy zur Ortssuche über [Photon](https://photon.komoot.io/) (OpenStreetMap-Daten), inkl. Caching |
| `lib/Widget.php` + `templates/app.tpl.php` | UIkit3-Oberfläche, von Modul und Backend-Testseite gemeinsam genutzt |
| `lib/Installer.php` | Modul-Installation/-Aktualisierung, geteilt zwischen `install.php` und `update.php` |
| `assets/buechsenlicht.js` | Live-Vorschläge, NOAA-Sonnenberechnung, Kalenderaufbau, ICS-Download und Abo-Link – vollständig im Browser |
| `assets/buechsenlicht.css` | UIkit3-Theming (dunkles Waldgrün) + Micro-Animationen |

Die Sonnenberechnung ist bewusst **doppelt implementiert** (PHP für den Feed, JavaScript für
Vorschau/Download) – beide folgen exakt demselben NOAA-Algorithmus und liefern identische
Ergebnisse (sekundengenau geprüft). Die Jagdzeiten-Daten selbst existieren dagegen nur einmal in
PHP und werden dem Frontend als JSON übergeben, um Divergenzen zwischen Server und Client
auszuschließen.

## Bekannte Einschränkungen

- Für 5 Bundesländer stammen die Jagdzeiten aus einer Zweitquelle statt dem Verordnungstext (siehe
  oben) – vor produktivem Einsatz dort ggf. manuell gegenprüfen.
- Rotwild, Damwild/Sikawild und Muffelwild werden je Bundesland nur als eine Klasse geführt, auch
  wo das Landesrecht weiter unterteilt (Details im jeweiligen `hinweis`-Feld).
- Die Ortssuche ist auf deutsche Orte begrenzt (`countrycode=DE`-Filter).

## Lizenz

MIT, © KLXM Crossmedia GmbH.
