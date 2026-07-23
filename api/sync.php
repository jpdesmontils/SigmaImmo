<?php
// ============================================================
// ImmoAggregator — API sync.php
// Reçoit les données de l'extension Chrome et les persiste
// PHP 7.0+, pas de dépendances
// ============================================================

require_once __DIR__ . '/logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { jsonError(405, 'Method not allowed'); }

// ── Config ────────────────────────────────────────────────────
define('API_KEY',       file_get_contents('keyfile.txt') ?: 'CHANGE_ME');
define('DATA_DIR',      __DIR__ . '/../data/');
define('FAVORITES_FILE', DATA_DIR . 'favorites.json');
define('MAX_FAVORITES', 1000);

// ── Auth ──────────────────────────────────────────────────────
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== API_KEY) {
    jsonError(401, 'Unauthorized');
}

// ── Body ──────────────────────────────────────────────────────
$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data || !is_array($data)) {
    jsonError(400, 'Invalid JSON body');
}

// ── Traitement ────────────────────────────────────────────────
$stats = ['favorites_added' => 0, 'favorites_updated' => 0];

if (!empty($data['favorites'])) {
    $stats = array_merge($stats, processFavorites($data['favorites']));
}

jsonOk(['ok' => true, 'stats' => $stats, 'ts' => time()]);

// ── Favoris ───────────────────────────────────────────────────
function processFavorites($incoming) {
    // Charger et s'assurer que le store est keyed par id
    $raw   = loadJson(FAVORITES_FILE, array());
    $store = deduplicateStore($raw, 'id');
    $added = 0; $updated = 0;

    appLog('app', 'sync.favorites_received', [
        'count' => count($incoming),
        'first_id' => isset($incoming[0]['id']) ? (string)$incoming[0]['id'] : null
    ]);

    foreach ($incoming as $listing) {
        // Construire l'URL depuis l'id si absente
        if (empty($listing['url']) && !empty($listing['id'])) {
            $listing['url'] = 'https://www.green-acres.fr/fr/properties/immobilier/' . $listing['id'] . '.htm';
        }
        if (empty($listing['url']) && !empty($listing['id'])) {
            $listing['url'] = $listing['id'];
        }
        if (empty($listing['url']) && empty($listing['id'])) {
            continue;
        }
        $key = !empty($listing['id']) ? $listing['id'] : normalizeUrl($listing['url']);

        // Géocodage si pas de coords — utiliser location si address vide
        if (empty($listing['coords'])) {
            $geoQuery = !empty($listing['address']) ? $listing['address']
                      : (!empty($listing['location']) ? $listing['location'] : '');
            if (!empty($geoQuery)) {
                $listing['coords'] = geocode($geoQuery);
                sleep(1); // Respect Nominatim rate limit 1 req/s
            }
        }

        if (!isset($store[$key])) {
            $store[$key] = sanitizeListing($listing);
            $added++;
        } else {
            // Merge : ne pas écraser les coords déjà présentes
            $existing = $store[$key];
            $merged = array_merge($existing, sanitizeListing($listing));
            if (!empty($existing['coords']) && empty($listing['coords'])) {
                $merged['coords'] = $existing['coords'];
            }
            $merged['updatedAt'] = time() * 1000;
            $store[$key] = $merged;
            $updated++;
        }
    }

    // Garder les N plus récents
    uasort($store, function($a, $b) { return ($b['capturedAt'] ? $b['capturedAt'] : 0) - ($a['capturedAt'] ? $a['capturedAt'] : 0); });
    if (count($store) > MAX_FAVORITES) {
        $store = array_slice($store, 0, MAX_FAVORITES, true);
    }

    // Sauvegarder en objet associatif (pas array_values) pour garder les clés
    saveJsonObject(FAVORITES_FILE, $store);
    return array('favorites_added' => $added, 'favorites_updated' => $updated);
}

// ── Dédupliquer un store (tableau indexé ou associatif) ────────
function deduplicateStore($store, $keyField) {
    $clean = array();
    $seen  = array();
    foreach ($store as $item) {
        if (!is_array($item)) continue;
        $k = isset($item[$keyField]) ? $item[$keyField] : null;
        if (!$k) {
            // Pas de clé — utiliser id ou url
            $k = isset($item['id']) ? $item['id'] : (isset($item['url']) ? $item['url'] : null);
        }
        if (!$k || isset($seen[$k])) continue;
        $seen[$k] = true;
        $clean[$k] = $item;
    }
    return $clean;
}

// ── Géocodage Nominatim ───────────────────────────────────────
function geocode($address) {
    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q='
         . urlencode($address);

    $ctx = stream_context_create(['http' => [
        'timeout' => 3,
        'header'  => "User-Agent: ImmoAggregator/1.0\r\n"
    ]]);

    $json = @file_get_contents($url, false, $ctx);
    if (!$json) return null;

    $results = json_decode($json, true);
    if (empty($results[0])) return null;

    return [
        'lat' => (float) $results[0]['lat'],
        'lng' => (float) $results[0]['lon']
    ];
}


// ── Sanitize ──────────────────────────────────────────────────
function sanitizeListing($l) {
    return [
        'id'          => $l['id']          ?? null,
        'url'         => $l['url']         ?? '',
        'title'       => substr(strip_tags($l['title']   ?? ''), 0, 200),
        'imageUrl'    => $l['imageUrl']    ?? $l['images'][0] ?? '',
        'images'      => $l['images']      ?? [],
        'price'       => is_numeric($l['price'] ?? null) ? (float)$l['price'] : null,
        'priceText'   => $l['priceText']   ?? '',
        'surface'     => is_numeric($l['surface'] ?? null) ? (float)$l['surface'] : null,
        'surfaceText' => $l['surfaceText'] ?? '',
        'location'    => substr(strip_tags($l['location']    ?? $l['address'] ?? ''), 0, 200),
        'address'     => substr(strip_tags($l['address']     ?? ''), 0, 300),
        'rooms'       => $l['rooms']       ?? '',
        'bedrooms'    => $l['bedrooms']    ?? '',
        'terrain'     => is_numeric($l['terrain'] ?? null) ? (float)$l['terrain'] : null,
        'description' => substr(strip_tags($l['description'] ?? ''), 0, 500),
        'features'    => is_array($l['features'] ?? null) ? $l['features'] : [],
        'agency'      => substr(strip_tags($l['agency'] ?? ''), 0, 150),
        'priceReduction' => substr(strip_tags($l['priceReduction'] ?? ''), 0, 50),
        'photoCount'  => is_numeric($l['photoCount'] ?? null) ? (int)$l['photoCount'] : null,
        'coords'      => $l['coords']      ?? null,
        'capturedAt'  => $l['capturedAt']  ?? time() * 1000,
        'scrapedAt'   => $l['scrapedAt']   ?? null,
        'source'      => $l['source']      ?? 'ga_favorite'
    ];
}

// ── Utils ─────────────────────────────────────────────────────
function normalizeUrl($url) {
    $parts = parse_url($url);
    return trim($parts['path'] ?? $url, '/');
}

function loadJson($file, $default) {
    if (!file_exists($file)) return $default;
    $content = file_get_contents($file);
    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : $default;
}

// Sauvegarder un objet associatif (garde les clés)
function saveJsonObject($file, $data) {
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $data = cleanUtf8($data);
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function cleanUtf8($value) {
    if (is_string($value)) {
        return iconv('UTF-8', 'UTF-8//IGNORE', $value);
    }
    if (is_array($value)) {
        foreach ($value as $k => $v) {
            $value[$k] = cleanUtf8($v);
        }
    }
    return $value;
}

function jsonOk($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError($code, $msg) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}
