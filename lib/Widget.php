<?php

namespace Buechsenlicht;

/**
 * Rendert die Büchsenlicht-Kalender-App (3 UIkit3-Cards). Wird sowohl vom Modul (Frontend-Ausgabe)
 * als auch von der Backend-Testseite aufgerufen, damit exakt dieselbe Oberfläche getestet wird,
 * die auch im Frontend ausgeliefert wird.
 */
class Widget
{
    private static bool $assetsEmitted = false;

    public static function render(array $options = []): string
    {
        $instanceId = 'bl-' . substr(sha1(uniqid('', true)), 0, 8);

        $config = [
            'feedBase' => \rex_url::frontendController(['rex-api-call' => 'buechsenlicht_feed'], false),
            'geocodeBase' => \rex_url::frontendController(['rex-api-call' => 'buechsenlicht_geocode'], false),
            'defaultVorMin' => 90,
            'defaultNachMin' => 90,
            'states' => self::exportStates(),
            'speciesOrder' => HuntingSeasons::SPECIES_ORDER,
            'stateNameMap' => self::exportStateNameMap(),
            'i18n' => [
                'legalNote' => 'Planungswerkzeug - keine amtliche Rechtsauskunft. Aktuelle Rechtslage und örtliche Anordnungen bitte eigenständig prüfen.',
            ],
        ];

        $addon = \rex_addon::get('buechsenlicht');
        $emitAssets = !self::$assetsEmitted;
        self::$assetsEmitted = true;

        $loadUikitCdn = !\rex_addon::get('uikit_theme_builder')->isAvailable();

        ob_start();
        include __DIR__ . '/../templates/app.tpl.php';
        return (string) ob_get_clean();
    }

    /** @return array<string, array{name:string, source:string, datenstand:string, hinweis:string, species:array<string, array<int, array{0:string,1:string}>>}> */
    private static function exportStates(): array
    {
        $export = [];
        foreach (HuntingSeasons::allStates() as $code => $name) {
            $meta = HuntingSeasons::meta($code);
            $species = [];
            foreach (HuntingSeasons::SPECIES_ORDER as $s) {
                $species[$s] = HuntingSeasons::periodsFor($code, $s);
            }
            $export[$code] = [
                'name' => $name,
                'source' => $meta['source'],
                'sourceUrl' => $meta['sourceUrl'],
                'datenstand' => $meta['datenstand'],
                'hinweis' => $meta['hinweis'],
                'thirdPartySource' => $meta['thirdPartySource'],
                'species' => $species,
            ];
        }
        return $export;
    }

    /** Roh-Zuordnung Nominatim-Bundeslandname -> Kennung, fürs Frontend gespiegelt. */
    private static function exportStateNameMap(): array
    {
        $map = [];
        foreach (HuntingSeasons::allStates() as $code => $name) {
            $map[mb_strtolower($name)] = $code;
        }
        // Zusätzliche gängige Varianten (z. B. "Freistaat Bayern").
        $map['freistaat bayern'] = 'BY';
        $map['freistaat sachsen'] = 'SN';
        $map['freistaat thüringen'] = 'TH';
        $map['freie hansestadt bremen'] = 'HB';
        $map['freie und hansestadt hamburg'] = 'HH';
        return $map;
    }
}
