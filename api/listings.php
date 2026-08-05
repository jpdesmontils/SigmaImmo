<?php
// ============================================================
// ImmoAggregator — API listings.php
// Sert les annonces persistées au frontend
// PHP 7.0+, pas de dépendances
// ============================================================

require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/analysis_types.php';

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
    $item['analyses'] = $safeId !== '' ? analysisSummaries(DATA_DIR, $safeId) : array_fill_keys(analysisTypes(), false);
    $item['latestAnalysis'] = latestAnalysisSummary($item['analyses']);
    $job = $safeId !== '' ? loadJson(ANALYSIS_JOBS_DIR . $safeId . '.json', []) : [];
    $item['analysisStatus'] = activeAnalysisStatus($job);
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
    list($field, $order) = normalizedSort($sort);
    $va = sortValue($a, $field);
    $vb = sortValue($b, $field);
    $aHasValue = $va !== null;
    $bHasValue = $vb !== null;
    if ($aHasValue !== $bHasValue) return $aHasValue ? -1 : 1;
    if (!$aHasValue) return 0;
    return $order === 'asc' ? $va - $vb : $vb - $va;
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

// "running" est réservé à la fenêtre où le worker attend la réponse du LLM.
function activeAnalysisStatus($job) {
    if (($job['status'] ?? '') === 'queued') return 'queued';
    if (($job['status'] ?? '') !== 'running') return null;
    $expiresAt = isset($job['lease_expires_at']) ? strtotime($job['lease_expires_at']) : false;
    return $expiresAt !== false && $expiresAt > time() ? 'running' : null;
}

function normalizedSort($sort) {
    $parts = explode('_', (string)$sort);
    $allowedFields = ['date', 'price', 'surface', 'score', 'yield', 'revenue', 'cashflow'];
    $field = in_array(isset($parts[0]) ? $parts[0] : '', $allowedFields, true) ? $parts[0] : 'date';
    $order = (isset($parts[1]) && $parts[1] === 'asc') ? 'asc' : 'desc';
    return [$field, $order];
}

function sortValue($item, $field) {
    $locatif = isset($item['analyses']['locatif']) && is_array($item['analyses']['locatif']) ? $item['analyses']['locatif'] : [];
    if ($field === 'price') return numericValue(isset($item['price']) ? $item['price'] : null);
    if ($field === 'surface') return numericValue(isset($item['surface']) ? $item['surface'] : null);
    if ($field === 'score') return numericValue(isset($item['latestAnalysis']['score']) ? $item['latestAnalysis']['score'] : null);
    if ($field === 'yield') return numericValue(isset($locatif['rendementNetPct']) ? $locatif['rendementNetPct'] : null);
    if ($field === 'revenue') return numericValue(isset($locatif['revenuBrutAnnuel']) ? $locatif['revenuBrutAnnuel'] : null);
    if ($field === 'cashflow') return numericValue(isset($locatif['cashflowMensuel']) ? $locatif['cashflowMensuel'] : null);
    return numericValue(isset($item['capturedAt']) ? $item['capturedAt'] : null);
}

function numericValue($value) {
    return is_numeric($value) ? (float)$value : null;
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
