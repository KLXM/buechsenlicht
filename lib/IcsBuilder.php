<?php

namespace Buechsenlicht;

/**
 * Baut eine valide .ics-Datei (RFC 5545) inkl. VTIMEZONE Europe/Berlin.
 */
class IcsBuilder
{
    /**
     * @param list<array{date:string, type:string, start:\DateTimeImmutable, end:\DateTimeImmutable, sunrise:\DateTimeImmutable, sunset:\DateTimeImmutable}> $events
     */
    public static function build(
        string $calendarName,
        string $species,
        string $ort,
        string $bundeslandName,
        array $events,
        string $uidNamespace,
    ): string {
        $lines = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//KLXM Crossmedia//Büchsenlicht-Kalender//DE';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';
        $lines[] = self::foldLine('X-WR-CALNAME:' . self::escapeText($calendarName));
        $lines[] = self::foldLine('X-WR-CALDESC:' . self::escapeText('Planungswerkzeug - keine amtliche Rechtsauskunft. Aktuelle Rechtslage und örtliche Anordnungen prüfen.'));
        $lines[] = 'X-WR-TIMEZONE:Europe/Berlin';
        $lines[] = 'REFRESH-INTERVAL;VALUE=DURATION:P1D';
        $lines[] = 'X-PUBLISHED-TTL:P1D';

        $lines = array_merge($lines, self::vtimezoneBlock());

        foreach ($events as $event) {
            $lines = array_merge($lines, self::eventBlock($event, $species, $ort, $bundeslandName, $uidNamespace));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * @param array{date:string, type:string, start:\DateTimeImmutable, end:\DateTimeImmutable, sunrise:\DateTimeImmutable, sunset:\DateTimeImmutable} $event
     * @return list<string>
     */
    private static function eventBlock(array $event, string $species, string $ort, string $bundeslandName, string $uidNamespace): array
    {
        $isMorning = 'morgens' === $event['type'];
        $label = $isMorning ? 'morgens' : 'abends';
        $summary = 'Büchsenlicht ' . $species . ' – ' . $label;

        $description = sprintf(
            '%s; %s; %s. Sonnenaufgang %s Uhr, Sonnenuntergang %s Uhr. '
                . 'Planungswert – aktuelle Rechtslage und örtliche Anordnungen prüfen.',
            $species,
            $ort,
            $bundeslandName,
            $event['sunrise']->format('H:i'),
            $event['sunset']->format('H:i'),
        );

        $uid = sprintf(
            '%s-%s-%s@buechsenlicht',
            $event['date'],
            $event['type'],
            substr(sha1($uidNamespace . '|' . $event['date'] . '|' . $event['type']), 0, 12),
        );

        $lines = [];
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . $uid;
        $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
        $lines[] = 'DTSTART;TZID=Europe/Berlin:' . $event['start']->format('Ymd\THis');
        $lines[] = 'DTEND;TZID=Europe/Berlin:' . $event['end']->format('Ymd\THis');
        $lines[] = self::foldLine('SUMMARY:' . self::escapeText($summary));
        $lines[] = self::foldLine('DESCRIPTION:' . self::escapeText($description));
        $lines[] = self::foldLine('LOCATION:' . self::escapeText($ort . ', ' . $bundeslandName));
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /** @return list<string> */
    private static function vtimezoneBlock(): array
    {
        return [
            'BEGIN:VTIMEZONE',
            'TZID:Europe/Berlin',
            'X-LIC-LOCATION:Europe/Berlin',
            'BEGIN:DAYLIGHT',
            'TZOFFSETFROM:+0100',
            'TZOFFSETTO:+0200',
            'TZNAME:CEST',
            'DTSTART:19700329T020000',
            'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
            'END:DAYLIGHT',
            'BEGIN:STANDARD',
            'TZOFFSETFROM:+0200',
            'TZOFFSETTO:+0100',
            'TZNAME:CET',
            'DTSTART:19701025T030000',
            'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
            'END:STANDARD',
            'END:VTIMEZONE',
        ];
    }

    private static function escapeText(string $text): string
    {
        $text = str_replace(['\\', "\n", "\r", ';', ','], ['\\\\', '\\n', '', '\\;', '\\,'], $text);
        return $text;
    }

    /** RFC 5545 Zeilenfaltung bei 75 Oktetten. */
    private static function foldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = '';
        $first = true;
        while ('' !== $line) {
            $chunkLen = $first ? 75 : 74;
            $folded .= ($first ? '' : "\r\n ") . substr($line, 0, $chunkLen);
            $line = substr($line, $chunkLen);
            $first = false;
        }

        return $folded;
    }
}
