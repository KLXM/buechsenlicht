<?php

namespace Buechsenlicht;

/**
 * Sonnenauf- und -untergangsberechnung nach dem NOAA-Sonnenalgorithmus
 * (NOAA Solar Calculator, General Solar Position Calculations).
 *
 * Die Berechnung erfolgt bewusst rein in UTC (Zeitzonen-Offset 0). Für die geografische
 * Breite/Länge Deutschlands liegen Sonnenauf- und -untergang ganzjährig weit genug von
 * Mitternacht UTC entfernt entfernt, sodass das UTC-Kalenderdatum stets mit dem gewünschten
 * Datum übereinstimmt. Die Umrechnung nach Europe/Berlin (inkl. korrekter Sommer-/Winterzeit)
 * übernimmt anschließend DateTimeZone.
 */
class SunCalc
{
    /**
     * @return array{sunrise: ?\DateTimeImmutable, sunset: ?\DateTimeImmutable} Zeiten in Europe/Berlin
     */
    public static function sunTimes(int $year, int $month, int $day, float $lat, float $lon): array
    {
        $jd = self::julianDay($year, $month, $day);
        $t = ($jd - 2451545.0) / 36525.0;

        $l0 = self::normalizeDeg(280.46646 + $t * (36000.76983 + $t * 0.0003032));
        $m = 357.52911 + $t * (35999.05029 - 0.0001537 * $t);
        $e = 0.016708634 - $t * (0.000042037 + 0.0000001267 * $t);

        $mRad = deg2rad($m);
        $c = sin($mRad) * (1.914602 - $t * (0.004817 + 0.000014 * $t))
            + sin(2 * $mRad) * (0.019993 - 0.000101 * $t)
            + sin(3 * $mRad) * 0.000289;

        $trueLong = $l0 + $c;
        $appLong = $trueLong - 0.00569 - 0.00478 * sin(deg2rad(125.04 - 1934.136 * $t));

        $meanObliq = 23 + (26 + (21.448 - $t * (46.815 + $t * (0.00059 - $t * 0.001813))) / 60) / 60;
        $obliqCorr = $meanObliq + 0.00256 * cos(deg2rad(125.04 - 1934.136 * $t));

        $declin = rad2deg(asin(sin(deg2rad($obliqCorr)) * sin(deg2rad($appLong))));

        $y = tan(deg2rad($obliqCorr / 2)) ** 2;
        $l0Rad = deg2rad($l0);
        $eqTime = 4 * rad2deg(
            $y * sin(2 * $l0Rad)
            - 2 * $e * sin($mRad)
            + 4 * $e * $y * sin($mRad) * cos(2 * $l0Rad)
            - 0.5 * $y * $y * sin(4 * $l0Rad)
            - 1.25 * $e * $e * sin(2 * $mRad)
        );

        $latRad = deg2rad($lat);
        $declinRad = deg2rad($declin);
        $haArg = cos(deg2rad(90.833)) / (cos($latRad) * cos($declinRad)) - tan($latRad) * tan($declinRad);

        if ($haArg > 1.0 || $haArg < -1.0) {
            // Polarnacht bzw. Polartag - für Deutschland praktisch nicht relevant.
            return ['sunrise' => null, 'sunset' => null];
        }

        $haSunrise = rad2deg(acos($haArg));

        $solarNoonFraction = (720 - 4 * $lon - $eqTime) / 1440;
        $sunriseFraction = $solarNoonFraction - $haSunrise * 4 / 1440;
        $sunsetFraction = $solarNoonFraction + $haSunrise * 4 / 1440;

        $utc = new \DateTimeZone('UTC');
        $berlin = new \DateTimeZone('Europe/Berlin');
        $base = new \DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day), $utc);

        $sunrise = $base->modify(self::secondsFromFraction($sunriseFraction) . ' seconds')->setTimezone($berlin);
        $sunset = $base->modify(self::secondsFromFraction($sunsetFraction) . ' seconds')->setTimezone($berlin);

        return ['sunrise' => $sunrise, 'sunset' => $sunset];
    }

    private static function secondsFromFraction(float $fraction): int
    {
        return (int) round($fraction * 86400);
    }

    private static function normalizeDeg(float $deg): float
    {
        $deg = fmod($deg, 360);
        return $deg < 0 ? $deg + 360 : $deg;
    }

    /** Julian Day für 12:00 UTC des Gregorianischen Kalenderdatums. */
    private static function julianDay(int $year, int $month, int $day): float
    {
        $a = intdiv(14 - $month, 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;

        $jdn = $day + intdiv(153 * $m + 2, 5) + 365 * $y + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;

        return (float) $jdn;
    }
}
