<?php
// ============================================================
// ImmoAggregator — API listings.php
// Sert les annonces persistées au frontend
// PHP 7.0+, pas de dépendances
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError(405, 'Method not allowed');
}

// ── Config ────────────────────────────────────────────────────
define('DATA_DIR', __DIR__ . '/../data/');
define('FAVORITES_FILE', DATA_DIR . 'favorites.json');
define('CRITEO_FILE', DATA_DIR . 'criteo.json');
define('MERGED_FILE', DATA_DIR . 'merged.json');
define('DEBUG_FILE', DATA_DIR . 'debug_listings.log');

// ── Debug ─────────────────────────────────────────────────────
function debugLog($label, $data = null) {
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0755, true);
    }

    $line = date('Y-m-d H:i:s') . ' | ' . $label;

    if ($data !== null) {
        $line .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    @file_put_contents(DEBUG_FILE, $line . "\n", FILE_APPEND);
}

debugLog('REQUEST', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'query' => $_GET,
    'data_dir' => DATA_DIR,
    'favorites_exists' => file_exists(FAVORITES_FILE),
    'criteo_exists' => file_exists(CRITEO_FILE),
    'merged_exists' => file_exists(MERGED_FILE)
]);

// ── Query params ──────────────────────────────────────────────
$type = isset($_GET['type']) ? $_GET['type'] : 'merged';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;

// ── Load data ─────────────────────────────────────────────────
if ($type === 'favorites') {
    $items = array_values(loadJson(FAVORITES_FILE, []));
    foreach ($items as &$item) {
        $item['_type'] = 'favorite';
    }
    unset($item);
} elseif ($type === 'criteo') {
    $items = array_values(loadJson(CRITEO_FILE, []));
    foreach ($items as &$item) {
        $item['_type'] = 'criteo';
    }
    unset($item);
} else {
    if (file_exists(MERGED_FILE)) {
        $items = array_values(loadJson(MERGED_FILE, []));
    } else {
        $favorites = array_values(loadJson(FAVORITES_FILE, []));
        $criteo = array_values(loadJson(CRITEO_FILE, []));

        foreach ($favorites as &$f) {
            $f['_type'] = 'favorite';
        }
        unset($f);

        foreach ($criteo as &$c) {
            $c['_type'] = 'criteo';
        }
        unset($c);

        $items = array_merge($favorites, $criteo);
    }
}

// ── Search ────────────────────────────────────────────────────
if ($q !== '') {
    $needle = mb_strtolower($q, 'UTF-8');

    $items = array_values(array_filter($items, function($item) use ($needle) {
        $haystack = '';

        foreach (['title', 'location', 'address', 'description', 'priceText', 'url', 'destinationUrl'] as $field) {
            if (isset($item[$field])) {
                $haystack .= ' ' . $item[$field];
            }
        }

        $haystack = mb_strtolower($haystack, 'UTF-8');

        return strpos($haystack, $needle) !== false;
    }));
}

// ── Sort ──────────────────────────────────────────────────────
usort($items, function($a, $b) use ($sort) {
    if ($sort === 'price_asc') {
        return numericValue($a, 'price') - numericValue($b, 'price');
    }

    if ($sort === 'price_desc') {
        return numericValue($b, 'price') - numericValue($a, 'price');
    }

    if ($sort === 'surface_desc') {
        return numericValue($b, 'surface') - numericValue($a, 'surface');
    }

    if ($sort === 'surface_asc') {
        return numericValue($a, 'surface') - numericValue($b, 'surface');
    }

    $ca = isset($a['capturedAt']) ? $a['capturedAt'] : 0;
    $cb = isset($b['capturedAt']) ? $b['capturedAt'] : 0;

    return $cb - $ca;
});

// ── Limit ─────────────────────────────────────────────────────
$total = count($items);

if ($limit > 0 && $total > $limit) {
    $items = array_slice($items, 0, $limit);
}

// ── Response ──────────────────────────────────────────────────
$response = [
    'ok' => true,
    'type' => $type,
    'query' => $q,
    'sort' => $sort,
    'total' => $total,
    'count' => count($items),
    'items' => $items,
    'debug' => [
        'favorites_file' => fileInfo(FAVORITES_FILE),
        'criteo_file' => fileInfo(CRITEO_FILE),
        'merged_file' => fileInfo(MERGED_FILE)
    ],
    'ts' => time()
];

debugLog('RESPONSE', [
    'type' => $type,
    'total' => $total,
    'count' => count($items),
    'first' => isset($items[0]) ? $items[0] : null
]);

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;

// ── Utils ─────────────────────────────────────────────────────
function loadJson($file, $default) {
    if (!file_exists($file)) {
        debugLog('LOAD_JSON_MISSING', ['file' => $file]);
        return $default;
    }

    $content = file_get_contents($file);

    if ($content === false || trim($content) === '') {
        debugLog('LOAD_JSON_EMPTY', ['file' => $file]);
        return $default;
    }

    $decoded = json_decode($content, true);

    if (!is_array($decoded)) {
        debugLog('LOAD_JSON_INVALID', [
            'file' => $file,
            'error' => json_last_error_msg(),
            'preview' => substr($content, 0, 500)
        ]);
        return $default;
    }

    debugLog('LOAD_JSON_OK', [
        'file' => $file,
        'count' => count($decoded)
    ]);

    return $decoded;
}

function numericValue($item, $field) {
    if (!isset($item[$field]) || !is_numeric($item[$field])) {
        return 0;
    }

    return (float)$item[$field];
}

function fileInfo($file) {
    return [
        'path' => $file,
        'exists' => file_exists($file),
        'size' => file_exists($file) ? filesize($file) : 0,
        'writable' => file_exists($file) ? is_writable($file) : null,
        'modified' => file_exists($file) ? filemtime($file) : null
    ];
}

function jsonError($code, $msg) {
    http_response_code($code);
    echo json_encode([
        'ok' => false,
        'error' => $msg
    ], JSON_UNESCAPED_UNICODE);
    exit;
}