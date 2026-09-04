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
