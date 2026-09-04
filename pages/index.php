<?php

echo rex_view::title('Büchsenlicht-Kalender · Testseite');

echo rex_view::info(
    'Diese Seite testet den Baustein 1:1 so, wie ihn das Modul „Büchsenlicht-Kalender“ im Frontend '
    . 'ausgibt (Ort bestimmen, Wildart wählen, Kalender erstellen, ICS herunterladen oder als '
    . 'webcal-Abo einbinden). Die Jagdzeiten-Datenbasis liegt in <code>lib/HuntingSeasons.php</code> '
    . 'und kann dort je Bundesland und Wildart gepflegt werden.'
);

echo \Buechsenlicht\Widget::render();
