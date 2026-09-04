<?php

namespace Buechsenlicht;

/**
 * Jagdzeiten-Datenbasis für alle 16 deutschen Bundesländer.
 *
 * Wichtig: Dieses Werkzeug dient ausschließlich der Planung. Für Nordrhein-Westfalen sind die
 * Reh-Klassen wie vom Auftraggeber vorgegeben hinterlegt (Landesjagdzeitenverordnung NRW). Für alle
 * übrigen Länder werden mangels vollständiger Einzelrecherche je Bundesland die Basiswerte der
 * Bundesjagdzeitenverordnung (BJagdZV) hinterlegt und als solche gekennzeichnet - das Landesrecht
 * kann abweichende, meist großzügigere Zeiten festlegen. Die Daten müssen vor produktivem Einsatz
 * gegen die jeweils aktuelle Landesjagdzeitenverordnung geprüft werden (siehe "hinweis" je Land).
 */
class HuntingSeasons
{
    public const SPECIES_ORDER = [
        'Rehwild · alle Klassen',
        'Rehwild · Böcke',
        'Rehwild · Schmalrehe',
        'Rehwild · Ricken',
        'Rehwild · Kitze',
        'Rotwild',
        'Damwild / Sikawild',
        'Muffelwild',
        'Waschbär',
    ];

    /** Reh-Teilklassen, deren Schnittmenge "Rehwild · alle Klassen" ergibt. */
    private const REHWILD_SUBCLASSES = [
        'Rehwild · Böcke',
        'Rehwild · Schmalrehe',
        'Rehwild · Ricken',
        'Rehwild · Kitze',
    ];

    /** Zuordnung: von Nominatim gelieferter Bundesland-Name (deutsch) -> interne Länderkennung. */
    private const STATE_NAME_MAP = [
        'baden-württemberg' => 'BW',
        'bayern' => 'BY',
        'freistaat bayern' => 'BY',
        'berlin' => 'BE',
        'brandenburg' => 'BB',
        'bremen' => 'HB',
        'freie hansestadt bremen' => 'HB',
        'hamburg' => 'HH',
        'freie und hansestadt hamburg' => 'HH',
        'hessen' => 'HE',
        'mecklenburg-vorpommern' => 'MV',
        'niedersachsen' => 'NI',
        'nordrhein-westfalen' => 'NW',
        'rheinland-pfalz' => 'RP',
        'saarland' => 'SL',
        'sachsen' => 'SN',
        'freistaat sachsen' => 'SN',
        'sachsen-anhalt' => 'ST',
        'schleswig-holstein' => 'SH',
        'thüringen' => 'TH',
        'freistaat thüringen' => 'TH',
    ];

    private const STATE_NAMES = [
        'BW' => 'Baden-Württemberg',
        'BY' => 'Bayern',
        'BE' => 'Berlin',
        'BB' => 'Brandenburg',
        'HB' => 'Bremen',
        'HH' => 'Hamburg',
        'HE' => 'Hessen',
        'MV' => 'Mecklenburg-Vorpommern',
        'NI' => 'Niedersachsen',
        'NW' => 'Nordrhein-Westfalen',
        'RP' => 'Rheinland-Pfalz',
        'SL' => 'Saarland',
        'SN' => 'Sachsen',
        'ST' => 'Sachsen-Anhalt',
        'SH' => 'Schleswig-Holstein',
        'TH' => 'Thüringen',
    ];

    /**
     * Länder, deren amtliches Landesrechtsportal ein für automatisierten Abruf nicht lesbares
     * JavaScript-Frontend ist; die Jagdzeiten stammen dort aus der genannten, amtlich anerkannten
     * Drittquelle statt aus dem direkt gelesenen Verordnungstext. Wird im UI (Datenquellen-Modal,
     * Info-Box) sichtbar als solche gekennzeichnet.
     *
     * @var array<string, string>
     */
    private const THIRD_PARTY_SOURCES = [
        'BE' => 'umwelt-online.de (Verordnungstext-Mirror)',
        'MV' => 'Kosmos-Verlag Jagdzeiten-Übersicht',
        'RP' => 'Landesjagdverband Rheinland-Pfalz e. V.',
        'SL' => 'Vereinigung der Jäger des Saarlandes',
        'SH' => 'Landesjagdverband Schleswig-Holstein e. V.',
    ];

    /** @var array<string, array<string, array<int, array{0:string,1:string}>>>|null */
    private static ?array $data = null;

    public static function stateName(string $code): ?string
    {
        return self::STATE_NAMES[$code] ?? null;
    }

    public static function thirdPartySource(string $code): ?string
    {
        return self::THIRD_PARTY_SOURCES[$code] ?? null;
    }

    /** @return array<string, string> Länderkennung => Name, alphabetisch nach Name sortiert */
    public static function allStates(): array
    {
        $states = self::STATE_NAMES;
        asort($states, SORT_STRING);
        return $states;
    }

    /**
     * Ordnet einen von Nominatim gelieferten Bundesland-Namen einer internen Länderkennung zu.
     */
    public static function resolveStateCode(string $stateName): ?string
    {
        $key = mb_strtolower(trim($stateName));
        return self::STATE_NAME_MAP[$key] ?? null;
    }

    /**
     * @return array{name:string, source:string, datenstand:string, hinweis:string}
     */
    public static function meta(string $stateCode): ?array
    {
        $all = self::data();
        if (!isset($all[$stateCode])) {
            return null;
        }
        return [
            'name' => self::STATE_NAMES[$stateCode],
            'source' => $all[$stateCode]['source'],
            'sourceUrl' => $all[$stateCode]['sourceUrl'],
            'datenstand' => $all[$stateCode]['datenstand'],
            'hinweis' => $all[$stateCode]['hinweis'],
            'thirdPartySource' => self::thirdPartySource($stateCode),
        ];
    }

    /** @return array<int, array{0:string,1:string}> */
    public static function periodsFor(string $stateCode, string $species): array
    {
        $all = self::data();
        if (!isset($all[$stateCode])) {
            return [];
        }

        if ('Rehwild · alle Klassen' === $species) {
            $lists = [];
            foreach (self::REHWILD_SUBCLASSES as $sub) {
                $lists[] = $all[$stateCode]['species'][$sub] ?? [];
            }
            return self::intersectAll($lists);
        }

        return $all[$stateCode]['species'][$species] ?? [];
    }

    /** @return list<string> */
    public static function speciesFor(string $stateCode): array
    {
        $all = self::data();
        if (!isset($all[$stateCode])) {
            return [];
        }
        return self::SPECIES_ORDER;
    }

    /**
     * Prüft, ob ein Kalendertag (Y-m-d) innerhalb einer der übergebenen Perioden liegt.
     * Perioden mit Start > Ende gelten als über den Jahreswechsel laufend.
     *
     * @param array<int, array{0:string,1:string}> $periods
     */
    public static function isDateInSeason(string $monthDay, array $periods): bool
    {
        foreach ($periods as [$start, $end]) {
            if ($start <= $end) {
                if ($monthDay >= $start && $monthDay <= $end) {
                    return true;
                }
            } else {
                if ($monthDay >= $start || $monthDay <= $end) {
                    return true;
                }
            }
        }
        return false;
    }

    // ---------------------------------------------------------------------
    // Schnittmengen-Berechnung (Reh "alle Klassen")
    // ---------------------------------------------------------------------

    /** @param list<array<int, array{0:string,1:string}>> $lists */
    private static function intersectAll(array $lists): array
    {
        $lists = array_values(array_filter($lists, static fn ($l) => [] !== $l));
        if ([] === $lists) {
            return [];
        }

        $result = $lists[0];
        for ($i = 1; $i < count($lists); ++$i) {
            $result = self::intersectPeriodLists($result, $lists[$i]);
            if ([] === $result) {
                break;
            }
        }
        return $result;
    }

    /**
     * @param array<int, array{0:string,1:string}> $a
     * @param array<int, array{0:string,1:string}> $b
     * @return array<int, array{0:string,1:string}>
     */
    private static function intersectPeriodLists(array $a, array $b): array
    {
        $rangesA = self::periodsToLinearRanges($a);
        $rangesB = self::periodsToLinearRanges($b);

        $intersected = [];
        foreach ($rangesA as [$s1, $e1]) {
            foreach ($rangesB as [$s2, $e2]) {
                $s = max($s1, $s2);
                $e = min($e1, $e2);
                if ($s <= $e) {
                    $intersected[] = [$s, $e];
                }
            }
        }

        return self::linearRangesToPeriods(self::mergeAndFoldRanges($intersected));
    }

    /**
     * Wandelt Perioden (ggf. mit Jahreswechsel) in nicht-wechselnde Tag-des-Jahres-Bereiche
     * (1..365, Referenzjahr ohne Schaltjahr) um.
     *
     * @param array<int, array{0:string,1:string}> $periods
     * @return list<array{0:int,1:int}>
     */
    private static function periodsToLinearRanges(array $periods): array
    {
        $ranges = [];
        foreach ($periods as [$start, $end]) {
            $s = self::mmddToDayOfYear($start);
            $e = self::mmddToDayOfYear($end);
            if ($s <= $e) {
                $ranges[] = [$s, $e];
            } else {
                $ranges[] = [$s, 365];
                $ranges[] = [1, $e];
            }
        }
        return $ranges;
    }

    /**
     * @param list<array{0:int,1:int}> $ranges
     * @return list<array{0:int,1:int}> Bereiche, ggf. ein einzelner Bereich mit s>e als Jahreswechsel-Markierung
     */
    private static function mergeAndFoldRanges(array $ranges): array
    {
        if ([] === $ranges) {
            return [];
        }

        usort($ranges, static fn ($x, $y) => $x[0] <=> $y[0]);

        $merged = [];
        foreach ($ranges as $range) {
            if ([] === $merged) {
                $merged[] = $range;
                continue;
            }
            $last = &$merged[count($merged) - 1];
            if ($range[0] <= $last[1] + 1) {
                $last[1] = max($last[1], $range[1]);
            } else {
                $merged[] = $range;
            }
            unset($last);
        }

        // Jahreswechsel-Verschmelzung: endet der letzte Bereich am Jahresende (365) und beginnt
        // der erste Bereich am Jahresanfang (1), gehören beide zyklisch zusammen.
        if (count($merged) > 1) {
            $first = $merged[0];
            $lastIdx = count($merged) - 1;
            $last = $merged[$lastIdx];
            if (1 === $first[0] && 365 === $last[1]) {
                array_shift($merged);
                array_pop($merged);
                $merged[] = [$last[0], $first[1]]; // s > e => Jahreswechsel
            }
        }

        return $merged;
    }

    /**
     * @param list<array{0:int,1:int}> $ranges
     * @return array<int, array{0:string,1:string}>
     */
    private static function linearRangesToPeriods(array $ranges): array
    {
        $periods = [];
        foreach ($ranges as [$s, $e]) {
            $periods[] = [self::dayOfYearToMmdd($s), self::dayOfYearToMmdd($e)];
        }
        return $periods;
    }

    private static function mmddToDayOfYear(string $mmdd): int
    {
        [$m, $d] = array_map('intval', explode('-', $mmdd));
        // Referenzjahr 2001 (kein Schaltjahr) - dient nur der abstrakten Perioden-Arithmetik.
        $ts = mktime(0, 0, 0, $m, $d, 2001);
        return (int) date('z', $ts) + 1; // 1..365
    }

    private static function dayOfYearToMmdd(int $day): string
    {
        $day = max(1, min(365, $day));
        $ts = mktime(0, 0, 0, 1, $day, 2001);
        return date('m-d', $ts);
    }

    // ---------------------------------------------------------------------
    // Datenbasis
    // ---------------------------------------------------------------------

    /**
     * Jagdzeiten je Bundesland, recherchiert gegen die jeweils aktuelle Landesverordnung
     * (Abruf 2026-09-04). Wo eine Wildart in der Landesverordnung nicht eigenständig geregelt
     * ist, wird ersatzweise der Bundesjagdzeitenverordnung-Wert (JagdzeitV 1977, § 1,
     * https://www.gesetze-im-internet.de/jagdzeitv_1977/) verwendet - im jeweiligen "hinweis"
     * vermerkt. Wildarten mit weiteren Alters-/Geschlechtsklassen als hier geführt (z. B.
     * Schmaltiere/Schmalspießer bei Rot-/Damwild mit abweichenden, meist früheren Zeiten) werden
     * auf die Hauptklasse ("Hirsche und Alttiere" bzw. Äquivalent) reduziert - diese
     * Vereinfachung ist ebenfalls im "hinweis" vermerkt, nicht verschwiegen.
     *
     * Für mehrere Länder war das amtliche Landesrechtsportal ein für automatisierten Abruf nicht
     * lesbares JavaScript-Frontend (Berlin, Mecklenburg-Vorpommern, Rheinland-Pfalz, Saarland,
     * Schleswig-Holstein); dort wurden ersatzweise amtliche bzw. amtlich anerkannte
     * Sekundärquellen (Landesjagdverband-Übersichten, Ministeriums-PDFs) herangezogen und, wo
     * möglich, gegen weitere unabhängige Quellen abgeglichen - im jeweiligen "hinweis" mit ⚠
     * gekennzeichnet. Alle übrigen Länder wurden direkt gegen den amtlichen Verordnungstext
     * geprüft. Trotzdem gilt: **Planungswerkzeug, keine amtliche Rechtsauskunft** - insbesondere
     * bei ⚠-gekennzeichneten Ländern vor Nutzung gegen die Originalverordnung prüfen.
     *
     * @return array<string, array{source:string, sourceUrl:string, datenstand:string, hinweis:string, species:array<string, array<int, array{0:string,1:string}>>}>
     */
    public static function data(): array
    {
        if (null !== self::$data) {
            return self::$data;
        }

        return self::$data = [
            'BW' => [
                'source' => 'DVO JWMG (Verordnung zur Durchführung des Jagd- und Wildtiermanagementgesetzes), § 10',
                'sourceUrl' => 'https://baden-wuerttemberg.de/index.php?dl=1&eID=dumpFile&fn=GBl._2026_Nr._1_vom_21.01.2026-signed.pdf&r=462204&t=r&token=34126f6d6f963db3024d3fc818c3b8db865d2836',
                'datenstand' => 'GBl. Baden-Württemberg 2026 Nr. 1 vom 21.01.2026, § 10 DVO JWMG n. F. (in Kraft seit 22.01.2026)',
                'hinweis' => 'Rotwild-Hauptwert = Hirsche und Alttiere (Kälber identisch); Schmalspießer/Schmaltiere '
                    . 'zusätzlich 1.-15.6. Sikawild wird in BW abweichend von Damwild mit eigenen Zeiten geregelt '
                    . '(hier nicht separat abgebildet, siehe Originalverordnung). Muffelwild-Hauptwert = Schafe/'
                    . 'Lämmer; Widder zusätzlich 1.-31.5.',
                'species' => [
                    'Rehwild · Böcke' => [['05-01', '01-31']],
                    'Rehwild · Schmalrehe' => [['05-01', '01-31']],
                    'Rehwild · Ricken' => [['09-01', '01-31']],
                    'Rehwild · Kitze' => [['09-01', '01-31']],
                    'Rotwild' => [['08-01', '01-31']],
                    'Damwild / Sikawild' => [['09-01', '01-31']],
                    'Muffelwild' => [['09-01', '01-31']],
                    'Waschbär' => [['01-01', '12-31']],
                ],
            ],
            'BY' => [
                'source' => 'AVBayJG (Verordnung zur Ausführung des Bayerischen Jagdgesetzes), § 19',
                'sourceUrl' => 'https://www.gesetze-bayern.de/Content/Document/BayAVJG/true',
                'datenstand' => 'AVBayJG vom 01.03.1983, zuletzt geändert durch Verordnung vom 05.07.2023 (GVBl. S. 487)',
                'hinweis' => 'Muffelwild ist in § 19 AVBayJG nicht aufgeführt - Wert = Bundesjagdzeitenverordnung. '
                    . 'Rotwild-Hauptwert = Alttiere/übrige Hirsche (Kälber identisch); Schmaltiere/-spießer bereits '
                    . 'ab 1.6. Dam- und Sikawild werden in Bayern gemeinsam mit identischen Zeiten geregelt.',
                'species' => [
                    'Rehwild · Böcke' => [['05-01', '10-15']],
                    'Rehwild · Schmalrehe' => [['05-01', '01-15']],
                    'Rehwild · Ricken' => [['09-01', '01-15']],
                    'Rehwild · Kitze' => [['09-01', '01-15']],
                    'Rotwild' => [['08-01', '01-31']],
                    'Damwild / Sikawild' => [['09-01', '01-31']],
                    'Muffelwild' => [['08-01', '01-31']],
                    'Waschbär' => [['01-01', '12-31']],
                ],
            ],
            'BE' => [
                'source' => 'Verordnung über jagdbare Tierarten und Jagdzeiten (Berlin)',
                'sourceUrl' => 'https://gesetze.berlin.de/bsbe/document/jlr-JagdA_ZVBE2007V2P2',
                'datenstand' => 'Verordnung vom 21.02.2007, zuletzt geändert durch Verordnung vom 22.08.2025 (GVBl. S. 437)',
                'hinweis' => '⚠ Amtliches Portal (gesetze.berlin.de) ist eine JavaScript-Anwendung, automatisierter '
                    . 'Volltextabruf war nicht möglich - Werte über Sekundärquelle ermittelt, vor produktivem '
                    . 'Einsatz manuell gegen gesetze.berlin.de prüfen. Rotwild, Damwild/Sikawild und Muffelwild '
                    . 'sind in Berlin nicht eigenständig geregelt (Werte = Bundesjagdzeitenverordnung).',
                'species' => [
                    'Rehwild · Böcke' => [['04-01', '05-31'], ['08-01', '01-31']],
                    'Rehwild · Schmalrehe' => [['04-01', '05-31'], ['08-01', '01-31']],
                    'Rehwild · Ricken' => [['09-01', '01-31']],
                    'Rehwild · Kitze' => [['09-01', '01-31']],
                    'Rotwild' => [['08-01', '01-31']],
                    'Damwild / Sikawild' => [['09-01', '01-31']],
                    'Muffelwild' => [['08-01', '01-31']],
                    'Waschbär' => [['01-01', '12-31']],
                ],
            ],
            'BB' => [
                'source' => 'BbgJagdDV (Verordnung zur Durchführung des Jagdgesetzes für das Land Brandenburg), § 5',
                'sourceUrl' => 'https://bravors.brandenburg.de/verordnungen/bbgjagddv',
                'datenstand' => 'BbgJagdDV vom 28.06.2019, zuletzt geändert durch Verordnung vom 22.05.2024 (GVBl.II/24 Nr. 32/37)',
                'hinweis' => 'Rot- und Damwild werden in Brandenburg gemeinsam mit identischen Zeiten geregelt; '
                    . 'Sikawild nicht separat genannt (Wert = Bundesjagdzeitenverordnung). Rehwild fasst "Rehbock '
                    . 'und Schmalreh" sowie "Ricke und Kitz" jeweils zu einer Zeile zusammen. Schmalspießer/'
                    . 'Schmaltiere sowie Jährlingswidder/Schmalschaf zusätzlich 16.4.-31.5. Waschbär ganzjährig '
                    . '(mit Mink und Marderhund), vorbehaltlich Elterntierschutz nach § 22 Abs. 4 BJagdG.',
                'species' => [
                    'Rehwild · Böcke' => [['04-16', '05-31'], ['08-01', '01-31']],
                    'Rehwild · Schmalrehe' => [['04-16', '05-31'], ['08-01', '01-31']],
                    'Rehwild · Ricken' => [['08-01', '01-31']],
                    'Rehwild · Kitze' => [['08-01', '01-31']],
                    'Rotwild' => [['08-01', '01-31']],
                    'Damwild / Sikawild' => [['08-01', '01-31']],
                    'Muffelwild' => [['08-01', '01-31']],
                    'Waschbär' => [['01-01', '12-31']],
                ],
            ],
            'HB' => [
                'source' => 'Jagdzeitenverordnung in Bremen',
                'sourceUrl' => 'https://umwelt.bremen.de/sixcms/media.php/13/BremischeJagdzeiten_Uebersicht.pdf',
                'datenstand' => 'Verordnung vom 03.06.2019 (Brem.GBl. S. 442); amtliche Übersicht Stand 30.03.2020',
                'hinweis' => 'Nur einzelne Arten weichen vom Bundes-Default ab (Rehwild-Kitze/-Schmalrehe, '
                    . 'Waschbär); übrige Werte = Bundesjagdzeitenverordnung. Waschbär bremenspezifisch 16.7.-31.3.; '
                    . 'Jungwaschbären zusätzlich ganzjährig (hier mangels Altersklasse nicht separat abgebildet).',
                'species' => [
                    'Rehwild · Böcke' => [['05-01', '10-15']],
                    'Rehwild · Schmalrehe' => [['09-01', '01-31']],
                    'Rehwild · Ricken' => [['09-01', '01-31']],
                    'Rehwild · Kitze' => [['09-01', '01-31']],
                    'Rotwild' => [['08-01', '01-31']],
                    'Damwild / Sikawild' => [['09-01', '01-31']],
                    'Muffelwild' => [['08-01', '01-31']],
                    'Waschbär' => [['07-16', '03-31']],
                ],
            ],
            'HH' => [
                'source' => 'Verordnung über jagdrechtliche Regelungen (Hamburg)',
                'sourceUrl' => 'https://www.landesrecht-hamburg.de/bsha/document/jlr-NNLHH00006964NN00000000003',
                'datenstand' => 'Verordnung vom 01.04.2014 (HmbGVBl. S. 126)',
                'hinweis' => 'Rehwild-Böcke/-Ricken und Muffelwild sind in Hamburg nicht gesondert geregelt (Wert '
                    . '= Bundesjagdzeitenverordnung). Schmalrehe haben eine geteilte Jagdzeit (Frühjahr + Herbst). '
                    . 'Waschbär in Hamburg ganzjährig für ALLE Altersklassen (anders als in mehreren '
                    . 'Nachbarländern nicht auf Jungtiere beschränkt).',
                'species' => [
                    'Rehwild · Böcke' => [['05-01', '10-15']],
                    'Rehwild · Schmalrehe' => [['05-01', '06-15'], ['09-01', '01-31']],
                    'Rehwild · Ricken' => [['09-01', '01-31']],
                    'Rehwild · Kitze' => [['09-01', '01-31']],
                    'Rotwild' => [['08-01', '01-31']],
                    'Damwild / Sikawild' => [['09-01', '01-31']],
                    'Muffelwild' => [['08-01', '01-31']],
                    'Waschbär' => [['01-01', '12-31']],
                ],
            ],
            'HE' => [
                'source' => 'Hessische Jagdverordnung (HJagdV)',
                'sourceUrl' => 'https://www.rv.hessenrecht.hessen.de/bshe/document/jlr-JagdVHE2022V4P2',
                'datenstand' => 'HJagdV vom 24.10.2022',
                'hinweis' => 'Rehwild-Ricken, Rotwild "Hirsche und Alttiere" sowie Damwild/Sikawild "Hirsche und '
                    . 'Alttiere" sind in Hessen nicht gesondert geregelt (Wert = Bundesjagdzeitenverordnung, teils '
                    . 'zufällig deckungsgleich). Schmalspießer/Schmaltiere bei Rot-/Damwild sowie Jährlingswidder/'
                    . 'Schmalschaf bei Muffelwild zusätzlich 1.4.-31.5. Waschbär hessenspezifisch 1.8.-28.2.; '
                    . 'Jungtiere zusätzlich ganzjährig.',
                'species' => [
                    'Rehwild · Böcke' => [['04-01', '01-31']],
                    'Rehwild · Schmalrehe' => [['04-01', '01-31']],
                    'Rehwild · Ricken' => [['09-01', '01-31']],
                    'Rehwild · Kitze' => [['09-01', '01-31']],
                    'Rotwild' => [['08-01', '01-31']],
                    'Damwild / Sikawild' => [['09-01', '01-31']],
                    'Muffelwild' => [['08-01', '01-31']],
                    'Waschbär' => [['08-01', '02-28']],
                ],
            ],
            'MV' => [
                'source' => 'JagdZVO M-V (Jagdzeitenverordnung Mecklenburg-Vorpommern)',
                'sourceUrl' => 'https://www.landesrecht-mv.de/bsmv/document/jlr-JagdzeitVMV2009V8P1',
                'datenstand' => 'JagdZVO M-V vom 14.11.2008, zuletzt geändert durch Verordnung vom 25.01.2023',
                'hinweis' => '⚠ Amtliches Portal (landesrecht-mv.de, JavaScript-Anwendung) automatisiert nicht '
                    . 'lesbar - Werte über mehrfach querverifizierte Fachquelle (Stand 12.08.2025) ermittelt, vor '
                    . 'produktivem Einsatz manuell gegenprüfen. Rot-/Damwild fassen Hirsche, Alttiere UND Kälber '
                    . 'in einer Klasse zusammen (unüblich); Schmalspießer/-tiere zusätzlich 16.4.-31.1. Sikawild '
                    . 'eigenständig mit abweichenden Werten geregelt (hier nicht separat abgebildet). Waschbär '
                    . 'ganzjährig - ob nur für Jungtiere oder alle Altersklassen, konnte mangels Primärtext nicht '
                    . 'geklärt werden.',
                'species' => [
                    'Rehwild · Böcke' => [['04-16', '01-31']],
                    'Rehwild · Schmalrehe' => [['04-16', '01-31']],
                    'Rehwild · Ricken' => [['09-01', '01-31']],
                    'Rehwild · Kitze' => [['09-01', '01-31']],
                    'Rotwild' => [['08-01', '01-31']],
                    'Damwild / Sikawild' => [['09-01', '01-31']],
                    'Muffelwild' => [['08-01', '01-31']],
                    'Waschbär' => [['01-01', '12-31']],
                ],
            ],
            'NI' => [
                'source' => 'DVO-NJagdG (Verordnung zur Durchführung des Niedersächsischen Jagdgesetzes)',
                'sourceUrl' => 'https://www.ml.niedersachsen.de/download/163729/Aktuelle_Jagdzeiten_in_Niedersachsen_Stand_25.01.2021_nicht_vollstaendig_barrierefrei_.pdf',
                'datenstand' => 'DVO-NJagdG vom 23.05.2008, zuletzt geändert durch Verordnung vom 18.01.2021; Übersicht Stand 25.01.2021',
                'hinweis' => 'Rotwild-Schmaltiere/Schmalspießer zusätzlich 1.4.-15.5. Damwild-Schmaltiere/-spießer '
                    . 'zusätzlich 1.4.-15.5. (Sikawild ohne dieses Frühjahrsfenster). Primärwert Damwild/Sikawild = '
                    . 'Alttiere/Kälber; Hirsche beider Arten bereits ab 1.8. bejagbar.',
                'species' => [
                    'Rehwild · Böcke' => [['04-01', '01-31']],
                    'Rehwild · Schmalrehe' => [['04-01', '05-15'], ['09-01', '01-31']],
                    'Rehwild · Ricken' => [['09-01', '01-31']],
                    'Rehwild · Kitze' => [['09-01', '01-31']],
                    'Rotwild' => [['08-01', '01-31']],
                    'Damwild / Sikawild' => [['09-01', '01-31']],
                    'Muffelwild' => [['08-01', '01-31']],
                    'Waschbär' => [['07-16', '03-31']],
                ],
            ],
            'NW' => [
                'source' => 'Landesjagdzeitenverordnung NRW (LJZeitVO)',
                'sourceUrl' => 'https://recht.nrw.de/lrgv/rechtsverordnung/29032024-verordnung-ueber-die-jagdzeiten-landesjagdzeitenverordnung-ljzeitvo1/',
                'datenstand' => 'LJZeitVO NRW, Fassung vom 29.03.2024',
                'hinweis' => 'Werte gegen den Verordnungstext geprüft (Reh-Klassen vom Auftraggeber vorgegeben, '
                    . 'bestätigt). Rotwild und Damwild/Sikawild haben zusätzlich eine kurze Schmaltiere/-spießer-'
                    . 'Zeit (1.-31. Mai), hier mangels Unterklasse nicht separat abgebildet. Waschbär: '
                    . 'Jungwaschbären dürfen ganzjährig bejagt werden, für die übrigen gilt der hier hinterlegte '
                    . 'Zeitraum (1. August - 28. Februar).',
                'species' => [
                    'Rehwild · Böcke' => [['05-01', '01-31']],
                    'Rehwild · Schmalrehe' => [['05-01', '05-31'], ['09-01', '01-31']],
                    'Rehwild · Ricken' => [['09-01', '01-31']],
                    'Rehwild · Kitze' => [['09-01', '01-31']],
                    'Rotwild' => [['08-01', '01-31']],
                    'Damwild / Sikawild' => [['08-01', '01-31']],
                    'Muffelwild' => [['08-01', '01-31']],
                    'Waschbär' => [['08-01', '02-28']],
                ],
            ],
            'RP' => [
                'source' => 'Landesjagdverordnung (LJVO) Rheinland-Pfalz, § 42',
                'sourceUrl' => 'https://www.landesrecht.rlp.de/bsrp/document/jlr-JagdVRP2013pG8',
                'datenstand' => 'LJVO vom 25.07.2013, § 42; Übersicht Landesjagdverband RLP Stand 21.08.2013',
                'hinweis' => '⚠ Amtliches Portal (landesrecht.rlp.de, JavaScript-Anwendung) automatisiert nicht '
                    . 'lesbar - Werte über Verbandsquelle ermittelt. Zum 01.04.2026 ist ein neues Landesjagdgesetz '
                    . 'samt neuer LJVO in Kraft getreten; ob sich die Jagdzeiten dadurch geändert haben, konnte '
                    . 'nicht abschließend verifiziert werden (Stand September 2026 zitieren Drittquellen weiter '
                    . 'unverändert § 42 LJVO 2013) - bitte nach Veröffentlichung der neuen LJVO erneut prüfen. '
                    . 'Sikawild ist gesetzlich ganzjährig geregelt (hier nicht separat abgebildet).',
                'species' => [
                    'Rehwild · Böcke' => [['05-01', '01-31']],
                    'Rehwild · Schmalrehe' => [['05-01', '01-31']],
                    'Rehwild · Ricken' => [['09-01', '01-31']],
                    'Rehwild · Kitze' => [['09-01', '01-31']],
                    'Rotwild' => [['08-01', '01-31']],
                    'Damwild / Sikawild' => [['08-01', '01-31']],
                    'Muffelwild' => [['08-01', '01-31']],
                    'Waschbär' => [['08-01', '02-28']],
                ],
            ],
            'SL' => [
                'source' => 'DV-SJG (Verordnung zur Durchführung des Saarländischen Jagdgesetzes), Anlage 3',
                'sourceUrl' => 'https://recht.saarland.de/bssl/document/jlr-JagdGDVSL2000V25Anlage3',
                'datenstand' => 'DV-SJG vom 27.01.2000, Anlage 3 gültig seit 28.01.2022; Übersicht Vereinigung der Jäger des Saarlandes Stand 01.04.2025',
                'hinweis' => '⚠ Amtliches Portal (recht.saarland.de, JavaScript-Anwendung) automatisiert nicht '
                    . 'lesbar - Werte aus einer amtlich anerkannten Verbandsgrafik entnommen, mit zwei '
                    . 'unabhängigen Quellen abgeglichen. Sikawild nicht separat geführt.',
                'species' => [
                    'Rehwild · Böcke' => [['04-01', '01-31']],
                    'Rehwild · Schmalrehe' => [['04-01', '05-15'], ['09-01', '01-31']],
                    'Rehwild · Ricken' => [['09-01', '01-31']],
                    'Rehwild · Kitze' => [['09-01', '01-31']],
                    'Rotwild' => [['08-01', '01-31']],
                    'Damwild / Sikawild' => [['08-01', '01-31']],
                    'Muffelwild' => [['08-01', '01-31']],
                    'Waschbär' => [['01-01', '12-31']],
                ],
            ],
            'SN' => [
                'source' => 'Sächsische Jagdverordnung (SächsJagdVO), § 3 f.',
                'sourceUrl' => 'https://www.revosax.sachsen.de/vorschrift/12563-Saechsische-Jagdverordnung',
                'datenstand' => 'SächsJagdVO vom 27.08.2012 (SächsGVBl. S. 518), § 4 seit Erlass unverändert (konsolidierte Fassung gültig ab 01.10.2021)',
                'hinweis' => 'Rotwild "Hirsche und Alttiere" sowie allgemeines Muffelwild (außerhalb Nationalpark) '
                    . 'sind in Sachsen nicht eigenständig geregelt (Wert = Bundesjagdzeitenverordnung). Dam- und '
                    . 'Sikawild werden gemeinsam geregelt. Sonderregelungen im Nationalpark Sächsische Schweiz '
                    . '(u. a. ganzjährige Jagd auf Muffel- und Damwild dort) hier nicht abgebildet.',
                'species' => [
                    'Rehwild · Böcke' => [['04-16', '01-31']],
                    'Rehwild · Schmalrehe' => [['04-16', '01-31']],
                    'Rehwild · Ricken' => [['08-01', '01-31']],
                    'Rehwild · Kitze' => [['08-01', '01-31']],
                    'Rotwild' => [['08-01', '01-31']],
                    'Damwild / Sikawild' => [['08-01', '01-31']],
                    'Muffelwild' => [['08-01', '01-31']],
                    'Waschbär' => [['01-01', '12-31']],
                ],
            ],
            'ST' => [
                'source' => 'JagdZeitV i. V. m. § 19 LJagdG-DVO (Verordnung zur Durchführung des Landesjagdgesetzes für Sachsen-Anhalt)',
                'sourceUrl' => 'https://lvwa.sachsen-anhalt.de/fileadmin/Bibliothek/Politik_und_Verwaltung/LVWA/LVwA/Dokumente/3_wirtschaft_kultur_verbrschutz_bau/309/4_landwirtschaftumwelt/409/409h/Jagdzeiten.pdf',
                'datenstand' => 'Amtliche Übersicht des Landesverwaltungsamts Sachsen-Anhalt, berücksichtigt Verordnungen vom 21.06.2017 und 12.03.2024',
                'hinweis' => 'Rehwild-Böcke und -Schmalrehe haben identische, verkürzte Schonzeit (Verordnung vom '
                    . '21.06.2017). Rotwild-Hauptwert = Hirsche und Alttiere (Kälber identisch); Schmalspießer/'
                    . 'Schmaltiere mit abweichenden, teils früheren Zeiten. Damwild und Sikawild in einer '
                    . 'gemeinsamen Tabelle, Sika mit eigenen Enddaten je Klasse - hier nur der für beide geltende '
                    . 'Hirsche/Alttiere-Wert verwendet.',
                'species' => [
                    'Rehwild · Böcke' => [['04-15', '01-31']],
                    'Rehwild · Schmalrehe' => [['04-15', '01-31']],
                    'Rehwild · Ricken' => [['09-01', '01-31']],
                    'Rehwild · Kitze' => [['09-01', '01-31']],
                    'Rotwild' => [['08-01', '01-31']],
                    'Damwild / Sikawild' => [['09-01', '01-31']],
                    'Muffelwild' => [['08-01', '01-31']],
                    'Waschbär' => [['01-01', '12-31']],
                ],
            ],
            'SH' => [
                'source' => 'JagdZV SH (Landesverordnung über jagdbare Tierarten und über die Jagdzeiten)',
                'sourceUrl' => 'https://ljv-sh.de/wp-content/uploads/2025/02/LJV_Infoblatt-145x102_Jagd_u_Schonzeiten-Jan_2025_Ansicht.pdf',
                'datenstand' => 'JagdZV SH vom 06.03.2019, zuletzt geändert durch Verordnung vom 16.07.2024; Infoblatt Landesjagdverband SH Stand Januar 2025',
                'hinweis' => '⚠ Amtliches Portal (gesetze-rechtsprechung.sh.juris.de, JavaScript-Anwendung) '
                    . 'automatisiert nicht lesbar - Werte aus offizieller, den Verordnungstext zitierender '
                    . 'Verbandsübersicht ermittelt. Rotwild- und Damwild/Sikawild-Hauptwert = Hirsche/Alttiere; '
                    . 'Schmalspießer/Schmaltiere mit abweichenden, teils früheren Zeiten. Dam- und Sikawild werden '
                    . 'in SH gemeinsam geregelt.',
                'species' => [
                    'Rehwild · Böcke' => [['05-01', '01-31']],
                    'Rehwild · Schmalrehe' => [['05-01', '05-31'], ['09-01', '01-31']],
                    'Rehwild · Ricken' => [['09-01', '01-31']],
                    'Rehwild · Kitze' => [['09-01', '01-31']],
                    'Rotwild' => [['08-01', '01-31']],
                    'Damwild / Sikawild' => [['09-01', '01-31']],
                    'Muffelwild' => [['08-01', '01-31']],
                    'Waschbär' => [['01-01', '12-31']],
                ],
            ],
            'TH' => [
                'source' => 'ThürJZVO (Thüringer Jagdzeitenverordnung) i. V. m. ThürVSRVO (Verkürzung der Schonzeit für Rehböcke/Schmalrehe)',
                'sourceUrl' => 'https://parldok.thueringer-landtag.de/ParlDok/dokument/86054/gesetz_und_verordnungsblatt_nr_9_2022.pdf',
                'datenstand' => 'ThürJZVO vom 08.06.1999, zuletzt durch Gesetz vom 16.10.2019 geändert; ThürVSRVO vom 10.03.2022 (GVBl. Nr. 9/2022, S. 189), befristet bis 31.03.2027',
                'hinweis' => 'Böcke: Schonzeitende (Jagdbeginn 1. April) amtlich über ThürVSRVO 2022 bestätigt; das '
                    . 'hier hinterlegte Saisonende (15. Oktober) stammt aus nicht-amtlichen Sekundärquellen und '
                    . 'konnte nicht gegen den aktuellen Volltext geprüft werden. Rotwild-Hauptwert = Hirsche 2-'
                    . 'jährig und älter/Alttiere/Kälber; Schmalspießer zusätzlich ab 16.6. Sikawild in der '
                    . 'ThürJZVO nicht separat geregelt (Wert = Bundesjagdzeitenverordnung). Waschbär: § 1 Abs. 2 '
                    . 'ThürJZVO legt ausdrücklich "keine Schonzeit" fest.',
                'species' => [
                    'Rehwild · Böcke' => [['04-01', '10-15']],
                    'Rehwild · Schmalrehe' => [['04-01', '01-15']],
                    'Rehwild · Ricken' => [['09-01', '01-15']],
                    'Rehwild · Kitze' => [['09-01', '01-15']],
                    'Rotwild' => [['08-01', '01-15']],
                    'Damwild / Sikawild' => [['09-01', '01-15']],
                    'Muffelwild' => [['08-01', '01-15']],
                    'Waschbär' => [['01-01', '12-31']],
                ],
            ],
        ];
    }
}
