<?php

namespace Buechsenlicht\Api;

use Buechsenlicht\CalendarGenerator;
use Buechsenlicht\HuntingSeasons;
use Buechsenlicht\IcsBuilder;

/**
 * Öffentlicher, abonnierbarer ICS-Feed (webcal). Kein Login erforderlich - alle benötigten
 * Parameter (Ort, Koordinaten, Bundesland, Wildart, Büchsenlicht-Werte) werden als Query-Parameter
 * übergeben, sodass der Feed zustandslos bei jedem Abruf neu (heute bis heute + 5 Jahre) berechnet
 * werden kann. Kalender-Apps rufen diese URL periodisch erneut ab (Abonnement).
 */
class FeedApi extends \rex_api_function
{
    protected $published = true;

    public function execute(): \rex_api_result
    {
        $land = strtoupper(\rex_request('land', 'string', ''));
        $species = \rex_request('art', 'string', '');
        $lat = \rex_request('lat', 'float', 0.0);
        $lon = \rex_request('lon', 'float', 0.0);
        $ort = \rex_request('ort', 'string', '');
        $morgens = '1' === \rex_request('morgens', 'string', '1');
        $abends = '1' === \rex_request('abends', 'string', '1');
        $vorMin = max(0, min(240, \rex_request('vor', 'int', 90)));
        $nachMin = max(0, min(240, \rex_request('nach', 'int', 90)));

        $stateName = HuntingSeasons::stateName($land);

        $this->sendHeaders();

        if (
            null === $stateName
            || '' === trim($ort)
            || !in_array($species, HuntingSeasons::SPECIES_ORDER, true)
            || $lat < -90.0 || $lat > 90.0
            || $lon < -180.0 || $lon > 180.0
            || (!$morgens && !$abends)
        ) {
            echo IcsBuilder::build(
                'Büchsenlicht-Kalender - ungültige Anfrage',
                $species ?: 'unbekannt',
                $ort ?: 'unbekannt',
                $stateName ?? 'unbekannt',
                [],
                'error',
            );
            exit;
        }

        $tz = new \DateTimeZone('Europe/Berlin');
        $from = new \DateTimeImmutable('today', $tz);
        $to = $from->modify('+5 years');

        $events = CalendarGenerator::generate($land, $species, $lat, $lon, $morgens, $abends, $vorMin, $nachMin, $from, $to);

        $calendarName = 'Büchsenlicht · ' . $species . ' · ' . $ort;
        $uidNamespace = implode('|', [$land, $species, $lat, $lon, $vorMin, $nachMin, $morgens ? 1 : 0, $abends ? 1 : 0]);

        echo IcsBuilder::build($calendarName, $species, $ort, $stateName, $events, $uidNamespace);
        exit;
    }

    protected function requiresCsrfProtection(): bool
    {
        return false;
    }

    private function sendHeaders(): void
    {
        \rex_response::cleanOutputBuffers();
        header('Content-Type: text/calendar; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        $filename = 'Buechsenlicht.ics';
        header('Content-Disposition: inline; filename="' . $filename . '"');
    }
}
