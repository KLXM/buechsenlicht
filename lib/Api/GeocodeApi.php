<?php

namespace Buechsenlicht\Api;

/**
 * Öffentlicher Server-Proxy für die Ortssuche (Autovervollständigung + "Ort bestimmen").
 *
 * Nutzt Photon (photon.komoot.io, OpenStreetMap-Daten, Betreiber komoot) statt Nominatim: Nominatims
 * /search-Endpunkt ist nicht für Live-Vorschläge während der Eingabe gedacht und matcht
 * unvollständige Wörter nicht als Präfix (z. B. fand "Dui" dort nicht "Duisburg", sondern beliebige
 * Orte/POIs, deren Name oder Adresse zufällig "Dui" enthält - z. B. ein Sushi-Restaurant). Photon
 * ist von komoot gezielt für Autovervollständigung gebaut und matcht Präfixe korrekt.
 *
 * Serverseitiger Proxy aus denselben Gründen wie zuvor bei Nominatim: identifizierender User-Agent,
 * kurzer Cache pro Suchbegriff, damit nicht jeder Besucher-Browser einzeln gegen den öffentlichen
 * Dienst anfragt (Nutzungsrichtlinie: moderate Nutzung, https://github.com/komoot/photon#api).
 * Die Antwort wird serverseitig in eine schlanke, Nominatim-ähnliche Form transformiert
 * ({lat, lon, display_name, address: {city, state}}), damit das Frontend unverändert bleibt.
 */
class GeocodeApi extends \rex_api_function
{
    protected $published = true;

    private const ENDPOINT = 'https://photon.komoot.io/api/';

    /** Grobe Bounding Box Deutschland (West, Süd, Ost, Nord) als Ranking-Bias bei Photon. */
    private const GERMANY_BBOX = '5.8,47.2,15.1,55.1';

    /** Stadtstaaten: Photon liefert für diese keinen "state"-Wert, da die Stadt selbst das Land ist. */
    private const STADTSTAATEN = ['Berlin', 'Hamburg'];

    private const CACHE_TTL = 300;
    private const TIMEOUT = 6;

    public function execute(): \rex_api_result
    {
        \rex_response::cleanOutputBuffers();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $query = trim(\rex_request('q', 'string', ''));
        $limit = max(1, min(5, \rex_request('limit', 'int', 5)));

        if ('' === $query || mb_strlen($query) < 2) {
            echo '[]';
            exit;
        }

        $cacheKey = sha1($query . '|' . $limit);
        $cacheFile = \rex_path::addonCache('buechsenlicht', 'geocode/' . $cacheKey . '.json');

        if (is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < self::CACHE_TTL) {
            echo (string) file_get_contents($cacheFile);
            exit;
        }

        // Mehr Rohtreffer anfragen als benötigt, da anschließend nach Land gefiltert und
        // dedupliziert wird (Photons bbox ist nur ein weicher Ranking-Bias, kein harter Filter).
        $url = self::ENDPOINT . '?' . http_build_query([
            'q' => $query,
            'limit' => min(20, $limit * 4),
            'lang' => 'de',
            'osm_tag' => 'place',
            'bbox' => self::GERMANY_BBOX,
        ], '', '&');

        $geojson = $this->fetchPhoton($url);

        if (null === $geojson) {
            header('HTTP/1.1 502 Bad Gateway');
            echo json_encode(['error' => 'Ortssuche derzeit nicht erreichbar.']);
            exit;
        }

        $body = json_encode($this->transform($geojson, $limit), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            \rex_dir::create($dir);
        }
        file_put_contents($cacheFile, $body);

        echo $body;
        exit;
    }

    protected function requiresCsrfProtection(): bool
    {
        return false;
    }

    /**
     * @return list<array{lat: float, lon: float, display_name: string, address: array{city: string, state: string|null}}>
     */
    private function transform(array $geojson, int $limit): array
    {
        $seen = [];
        $result = [];

        foreach ($geojson['features'] ?? [] as $feature) {
            $props = $feature['properties'] ?? [];
            $coords = $feature['geometry']['coordinates'] ?? null;

            if ('DE' !== ($props['countrycode'] ?? null) || !is_array($coords) || 2 !== count($coords)) {
                continue;
            }

            $name = (string) ($props['name'] ?? '');
            if ('' === $name) {
                continue;
            }

            $state = $props['state'] ?? null;
            if (null === $state && in_array($name, self::STADTSTAATEN, true)) {
                $state = $name;
            }

            $dedupeKey = $name . '|' . $state;
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $displayParts = ($state === $name) ? [$name, 'Deutschland'] : array_filter([$name, $state, 'Deutschland']);

            $result[] = [
                'lat' => (float) $coords[1],
                'lon' => (float) $coords[0],
                'display_name' => implode(', ', $displayParts),
                'address' => [
                    'city' => $name,
                    'state' => $state,
                ],
            ];

            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    private function fetchPhoton(string $url): ?array
    {
        $version = \rex_addon::get('buechsenlicht')->getVersion() ?: '1.0.0';
        $host = \rex_server('HTTP_HOST', 'string', 'unknown-host');
        $userAgent = 'Buechsenlicht-Kalender/' . $version . ' (REDAXO AddOn; +https://' . $host . '/)';

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: {$userAgent}\r\nAccept: application/json\r\n",
                'timeout' => self::TIMEOUT,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if (false === $body) {
            return null;
        }

        $statusLine = $http_response_header[0] ?? '';
        if (!preg_match('/\s(2\d{2})\s/', $statusLine)) {
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data) || JSON_ERROR_NONE !== json_last_error()) {
            return null;
        }

        return $data;
    }
}
