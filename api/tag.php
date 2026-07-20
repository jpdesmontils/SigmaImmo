<?php
// ============================================================
// ImmoAggregator — API tag.php
// Met à jour le champ selection d'une annonce
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }

define('DATA_DIR',       __DIR__ . '/../data/');
define('FAVORITES_FILE', DATA_DIR . 'favorites.json');
define('CRITEO_FILE',    DATA_DIR . 'criteo.json');
define('MERGED_FILE',    DATA_DIR . 'merged.json');

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data || empty($data['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing id']);
    exit;
}

$id  = $data['id'];
$sel = isset($data['selection']) ? $data['selection'] : null;

// Valider la valeur
if ($sel !== null && $sel !== 'shortlist' && $sel !== 'ecartee') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid selection value']);
    exit;
}

$updated = false;

// Mettre à jour dans favorites.json
$favorites = loadJson(FAVORITES_FILE);
foreach ($favorites as $key => &$item) {
    if (isset($item['id']) && $item['id'] === $id) {
        if ($sel === null) {
            unset($item['selection']);
        } else {
            $item['selection'] = $sel;
        }
        $updated = true;
        break;
    }
}
unset($item);
if ($updated) saveJson(FAVORITES_FILE, $favorites);

// Mettre à jour dans criteo.json si pas trouvé
if (!$updated) {
    $criteo = loadJson(CRITEO_FILE);
    foreach ($criteo as $key => &$item) {
        if ((isset($item['id']) && $item['id'] === $id) ||
            (isset($item['imageUrl']) && $item['imageUrl'] === $id)) {
            if ($sel === null) unset($item['selection']);
            else $item['selection'] = $sel;
            $updated = true;
            break;
        }
    }
    unset($item);
    if ($updated) saveJson(CRITEO_FILE, $criteo);
}

// Rebuild merged.json
if ($updated) {
    $favs  = array_values(loadJson(FAVORITES_FILE));
    $crits = array_values(loadJson(CRITEO_FILE));
    foreach ($favs  as &$f) { $f['_type'] = 'favorite'; }
    foreach ($crits as &$c) { $c['_type'] = 'criteo'; }

    $merged = array();
    $seen   = array();
    foreach (array_merge($favs, $crits) as $item) {
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
}

echo json_encode(array('ok' => true, 'id' => $id, 'selection' => $sel, 'updated' => $updated));

function loadJson($file) {
    if (!file_exists($file)) return array();
    $decoded = json_decode(file_get_contents($file), true);
    return is_array($decoded) ? $decoded : array();
}

function saveJson($file, $data) {
    $data = cleanUtf8($data);
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function cleanUtf8($value) {
    if (is_string($value)) return iconv('UTF-8', 'UTF-8//IGNORE', $value);
    if (is_array($value)) {
        foreach ($value as $k => $v) $value[$k] = cleanUtf8($v);
    }
    return $value;
}