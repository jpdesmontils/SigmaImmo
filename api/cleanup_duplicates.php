<?php
// ============================================================
// ImmoAggregator — cleanup_duplicates.php
// Script à exécuter UNE SEULE FOIS pour dédupliquer
// les fichiers JSON existants corrompus avec doublons
// Appel : https://votre-serveur/sigma-immo/api/cleanup_duplicates.php
// À SUPPRIMER après exécution !
// ============================================================

header('Content-Type: application/json');

define('DATA_DIR',       __DIR__ . '/../data/');
define('FAVORITES_FILE', DATA_DIR . 'favorites.json');
define('CRITEO_FILE',    DATA_DIR . 'criteo.json');
define('MERGED_FILE',    DATA_DIR . 'merged.json');

$report = array();

// ── Nettoyer favorites.json ───────────────────────────────────
$favRaw = loadJson(FAVORITES_FILE);
$favClean = array();
$favSeen  = array();
$favDups  = 0;

foreach ($favRaw as $item) {
    if (!is_array($item)) continue;
    $k = isset($item['id']) ? $item['id']
       : (isset($item['url']) ? normalizeUrl($item['url']) : null);
    if (!$k) continue;
    if (isset($favSeen[$k])) { $favDups++; continue; }
    $favSeen[$k] = true;
    $favClean[$k] = $item;
}

$report['favorites'] = array(
    'before' => count($favRaw),
    'after'  => count($favClean),
    'duplicates_removed' => $favDups
);

saveJson(FAVORITES_FILE, $favClean);

// ── Nettoyer criteo.json ──────────────────────────────────────
$critRaw   = loadJson(CRITEO_FILE);
$critClean = array();
$critSeen  = array();
$critDups  = 0;

foreach ($critRaw as $item) {
    if (!is_array($item)) continue;
    $k = isset($item['imageUrl']) ? $item['imageUrl'] : null;
    if (!$k) continue;
    if (isset($critSeen[$k])) { $critDups++; continue; }
    $critSeen[$k] = true;
    $critClean[$k] = $item;
}

$report['criteo'] = array(
    'before' => count($critRaw),
    'after'  => count($critClean),
    'duplicates_removed' => $critDups
);

saveJson(CRITEO_FILE, $critClean);

// ── Rebuild merged.json propre ────────────────────────────────
$favorites = array_values($favClean);
$criteo    = array_values($critClean);

foreach ($favorites as &$f) { $f['_type'] = 'favorite'; }
foreach ($criteo    as &$c) { $c['_type'] = 'criteo'; }

$merged = array();
$seen   = array();
foreach (array_merge($favorites, $criteo) as $item) {
    $k = isset($item['id']) ? $item['id']
       : (isset($item['imageUrl']) ? $item['imageUrl'] : null);
    if (!$k || isset($seen[$k])) continue;
    $seen[$k] = true;
    $merged[] = $item;
}

usort($merged, function($a, $b) {
    $va = isset($a['capturedAt']) ? $a['capturedAt'] : 0;
    $vb = isset($b['capturedAt']) ? $b['capturedAt'] : 0;
    return $vb - $va;
});

saveJson(MERGED_FILE, $merged);

$report['merged'] = array(
    'total' => count($merged)
);

$report['status'] = 'OK - Supprimez ce fichier après exécution !';

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// ── Helpers ───────────────────────────────────────────────────
function loadJson($file) {
    if (!file_exists($file)) return array();
    $decoded = json_decode(file_get_contents($file), true);
    return is_array($decoded) ? $decoded : array();
}

function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function normalizeUrl($url) {
    $parts = parse_url($url);
    return trim(isset($parts['path']) ? $parts['path'] : $url, '/');
}