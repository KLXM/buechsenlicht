# Changelog

## 1.0.0

- Erste Version: Büchsenlicht-Kalender als REDAXO-AddOn (Geokodierung, Jagdzeiten-Datenbasis für
  alle 16 Bundesländer mit Schnittmengen-Logik für Rehwild, NOAA-Sonnenberechnung, ICS-Download,
  abonnierbarer ICS-Feed, Modul + Backend-Testseite, UIkit3-Oberfläche).
- Ortssuche auf serverseitigen Proxy umgestellt (kein direkter Browser-Zugriff auf externe
  Geocoding-Dienste) und von Nominatim auf Photon umgestellt (Nominatim matcht keine Präfixe für
  Live-Vorschläge). Layout: Ort/Revier jetzt volle Breite, Wildart/Kalender darunter im 50/50-Grid.
- Kalenderzeitraum (Download und Abo) auf 5 statt 10 Jahre begrenzt.
- Jagdzeiten-Basiswerte gegen die echten Verordnungstexte geprüft und korrigiert: BJagdZV-Baseline
  (Rehwild Schmalrehe/Kitze, Damwild/Sikawild) sowie NRW-Waschbär (war fälschlich ganzjährig
  hinterlegt, gilt laut LJZeitVO nur für Jungwaschbären) berichtigt. Quellenangaben inkl. Link je
  Bundesland in der Oberfläche ergänzt.
- Alle 16 Bundesländer einzeln gegen die jeweils aktuelle Landesverordnung recherchiert und mit
  eigenen Werten hinterlegt (vorher: 15 Länder auf einer geteilten Bundes-Baseline). „Datenquellen“-
  Modal ergänzt (Link im Banner) mit Quelle, Link und Datenstand je Bundesland; für 5 Länder mit
  technisch nicht auslesbarem Rechtsportal wird die benannte Zweitquelle ausgewiesen.
- Modul-Installation in `lib/Installer.php` extrahiert und zusätzlich aus `update.php` aufgerufen,
  damit Änderungen am Modul auch bei einem reinen Versions-Update ankommen (vorher nur bei
  Neuinstallation).
- Micro-Animationen ergänzt (Button-Hover/-Press, Lade-Spinner bei der Ortssuche, Fade-In für
  Ergebnis-/Vorschlagslisten, kurzes Aufblitzen der Info-Box bei Aktualisierung), respektiert
  `prefers-reduced-motion`.
- README komplett überarbeitet (Funktionsumfang, Einbindung, Architektur, Datenquellen).
- Klarstellung ergänzt (Datenquellen-Modal + README): Wo eine Landesverordnung eine Wildart nicht
  eigenständig regelt, gilt automatisch die Bundesjagdzeitenverordnung weiter - das ist geltendes
  Recht, keine Datenlücke, im Unterschied zu den 5 Ländern mit tatsächlicher Zweitquellen-Unsicherheit.
- „Rechtliches & Datenschutz“-Bereich ergänzt (Kostenfreiheit, Haftungsausschluss, Datenstand als
  benannter Wert `HuntingSeasons::RESEARCHED_AT`, Datenschutzhinweise zu Ortssuche und Kalender-Abo
  - der Abo-Link enthält Ort/Wildart/Einstellungen offen in der URL und liegt nach dem Abonnieren
  in der Kontrolle der jeweiligen Kalender-App/deren Cloud-Anbieter). Kurzhinweis dazu zusätzlich
  direkt im Abo-Bereich.
- Chrome bot auf dem Ort-Feld sein eigenes profilbasiertes Adress-Autofill an (z. B. gespeicherte
  Heim-/Arbeitsadresse), das `autocomplete="off"` bewusst ignoriert. Behoben über "readonly bis zum
  ersten Fokus" (Feld startet `readonly`, JS entfernt das Attribut bei Fokus/Mousedown) - verhindert
  zuverlässig, dass Chrome die Autofill-UI beim Laden anhängt, ohne die eigene Live-Vorschlagsliste
  zu beeinträchtigen.
- CSS/JS-Assets bekommen jetzt einen dateizeitbasierten Cache-Buster (`?v=<mtime>`) in den
  `<link>`/`<script>`-URLs, damit Browser nach einem Deploy nicht versehentlich eine ältere,
  bereits im HTTP-Cache liegende Version derselben URL weiterverwenden.
- UIkit-3-CDN-Fallback entfernt: Das AddOn lädt UIkit 3 nicht mehr selbst nach (auch nicht bedingt
  über eine `uikit_theme_builder`-Prüfung), sondern setzt konsequent voraus, dass die Website UIkit
  3 bereits selbst einbindet.
- Der Chrome-Fix reichte nicht für Safari: Dessen Kontakt-/Adress-Autofill matcht fortlaufend beim
  Tippen (nicht nur beim Laden) und überdeckte den ersten eigenen Vorschlag. Robuster gelöst, indem
  `.bl-ort-input` von `<input>` auf ein per CSS einzeilig dargestelltes `<textarea>` umgestellt
  wurde - native Adress-Autofill-Vorschläge sind eine input-spezifische Browserfunktion und greifen
  bei textarea in keinem Browser. Enter fügt dort keinen Zeilenumbruch mehr ein, sondern übernimmt
  den aktiven Vorschlag bzw. löst „Ort bestimmen“ aus, wenn kein Vorschlag aktiv ist.
- „Einzelnen Tag prüfen“-Modal ergänzt: beliebiges Datum wählen und sofort sehen, ob es für Ort +
  Wildart ein Jagdtag ist, inkl. Sonnenauf-/-untergang und Büchsenlicht-Fenstern. Dafür die
  Tageslogik aus `generateEvents()` in eine eigenständige, wiederverwendbare Funktion
  `computeDayInfo()` extrahiert (JS), die jetzt sowohl vom Mehrjahres-Kalender als auch vom neuen
  Modal genutzt wird - keine doppelte Logik. UIkit3-Icons ergänzt (Ort bestimmen, Kalender
  erstellen, Einzelnen Tag prüfen, ICS herunterladen, Ergebnis-Anzeige im neuen Modal).
- UIkit-Alert-Boxen (farbige Vollflächen-Hintergründe) durch weiße Cards mit Schatten und
  dezentem farbigem linkem Rand ersetzt (`.bl-panel`/`.bl-panel-success`/`-warning`/`-info`) -
  wirkt ruhiger und verträgt sich unabhängig vom Website-Theme. Betrifft: Bundesland-Erkennung,
  Kalender-Ergebnis, „keine Einträge“-Hinweis sowie beide Zustände im „Einzelnen Tag
  prüfen“-Modal. Eigene Fade-Slide-Animation durch UIkits `uk-animation-fade` ersetzt.
- „Datenquellen“-Tabelle lief in schmalen Modal-Fenstern (v. a. mobil) rechts aus dem sichtbaren
  Bereich. Behoben durch `table-layout: fixed` + `overflow-wrap: anywhere` auf den Zellen, damit
  lange Quellen-/Datenstand-Texte innerhalb ihrer Spalte umbrechen. Eine breitere
  `uk-modal-container`-Variante wurde ausprobiert, aber wieder verworfen: Bei ca. 1400px
  Fensterbreite ragte das dann sehr breite Modal hinter die fixierte Backend-Sidebar und schnitt
  stattdessen die erste Spalte ab - die Standardbreite plus Zeilenumbruch war die robustere Lösung.
