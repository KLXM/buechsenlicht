<?php

namespace Buechsenlicht;

/**
 * Erzeugt die Liste der Büchsenlicht-Termine für einen Ort/Zeitraum/Wildart.
 * Wird sowohl vom abonnierbaren ICS-Feed als auch (für die Vorschau) von der Backend-Testseite genutzt.
 */
class CalendarGenerator
{
    /**
     * @return list<array{date:string, type:string, start:\DateTimeImmutable, end:\DateTimeImmutable, sunrise:\DateTimeImmutable, sunset:\DateTimeImmutable}>
     */
    public static function generate(
        string $stateCode,
        string $species,
        float $lat,
        float $lon,
        bool $morgens,
        bool $abends,
        int $vorMin,
        int $nachMin,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $periods = HuntingSeasons::periodsFor($stateCode, $species);
        if ([] === $periods || (!$morgens && !$abends)) {
            return [];
        }

        $events = [];
        $cursor = $from;
        // Sicherheitsgrenze, damit ein fehlerhafter Aufruf nicht endlos läuft (max. Zeitraum + Puffer).
        $maxDays = (int) $from->diff($to)->days + 2;

        for ($i = 0; $i <= $maxDays && $cursor <= $to; ++$i, $cursor = $cursor->modify('+1 day')) {
            $monthDay = $cursor->format('m-d');
            if (!HuntingSeasons::isDateInSeason($monthDay, $periods)) {
                continue;
            }

            $sun = SunCalc::sunTimes((int) $cursor->format('Y'), (int) $cursor->format('n'), (int) $cursor->format('j'), $lat, $lon);
            if (null === $sun['sunrise'] || null === $sun['sunset']) {
                continue;
            }

            if ($morgens) {
                $events[] = [
                    'date' => $cursor->format('Y-m-d'),
                    'type' => 'morgens',
                    'start' => $sun['sunrise']->modify('-' . $vorMin . ' minutes'),
                    'end' => $sun['sunrise'],
                    'sunrise' => $sun['sunrise'],
                    'sunset' => $sun['sunset'],
                ];
            }
            if ($abends) {
                $events[] = [
                    'date' => $cursor->format('Y-m-d'),
                    'type' => 'abends',
                    'start' => $sun['sunset'],
                    'end' => $sun['sunset']->modify('+' . $nachMin . ' minutes'),
                    'sunrise' => $sun['sunrise'],
                    'sunset' => $sun['sunset'],
                ];
            }
        }

        return $events;
    }
}
