<?php
// ============================================================
// ImmoAggregator — API listings.php
// Sert les annonces persistées au frontend
// PHP 7.0+, pas de dépendances
// ============================================================

require_once __DIR__ . '/logger.php';

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
define('ANALYSIS_JOBS_DIR', DATA_DIR . 'analyses/jobs/');

// ── Journalisation ─────────────────────────────────────────────
appLog('app', 'listings.request', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'query' => $_GET,
    'favorites_exists' => file_exists(FAVORITES_FILE)
]);

// ── Query params ──────────────────────────────────────────────
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;

// ── Load data ─────────────────────────────────────────────────
$items = array_values(loadJson(FAVORITES_FILE, []));

// Expose uniquement la disponibilité locale des analyses, jamais leur contenu.
foreach ($items as &$item) {
    $id = isset($item['id']) ? (string)$item['id'] : '';
    $safeId = preg_match('/^[A-Za-z0-9_-]{1,180}$/', $id) ? $id : '';
    $item['analyses'] = [
        'locatif' => $safeId !== '' && is_file(DATA_DIR . 'analyses/locatif/' . $safeId . '.json'),
        'mdb' => $safeId !== '' && is_file(DATA_DIR . 'analyses/mdb/' . $safeId . '.json')
    ];
    $job = $safeId !== '' ? loadJson(ANALYSIS_JOBS_DIR . $safeId . '.json', []) : [];
    $item['analysisRunning'] = ($job['status'] ?? '') === 'running';
}
unset($item);

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
    'query' => $q,
    'sort' => $sort,
    'total' => $total,
    'count' => count($items),
    'items' => $items,
    'debug' => ['favorites_file' => fileInfo(FAVORITES_FILE)],
    'ts' => time()
];

appLog('app', 'RESPONSE', [
    'total' => $total,
    'count' => count($items),
    'first' => isset($items[0]) ? $items[0] : null
]);

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;

// ── Utils ─────────────────────────────────────────────────────
function loadJson($file, $default) {
    if (!file_exists($file)) {
        appLog('app', 'LOAD_JSON_MISSING', ['file' => $file]);
        return $default;
    }

    $content = file_get_contents($file);

    if ($content === false || trim($content) === '') {
        appLog('app', 'LOAD_JSON_EMPTY', ['file' => $file]);
        return $default;
    }

    $decoded = json_decode($content, true);

    if (!is_array($decoded)) {
        appLog('app', 'LOAD_JSON_INVALID', [
            'file' => $file,
            'error' => json_last_error_msg(),
            'preview' => substr($content, 0, 500)
        ]);
        return $default;
    }

    appLog('app', 'LOAD_JSON_OK', [
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
