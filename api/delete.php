<?php
// ============================================================
// ImmoAggregator — API delete.php
// Supprime une annonce des fichiers JSON
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }

define('API_KEY',        getenv('IMMO_API_KEY') ?: 'CHANGE_ME');
define('DATA_DIR',       __DIR__ . '/../data/');
define('FAVORITES_FILE', DATA_DIR . 'favorites.json');
define('CRITEO_FILE',    DATA_DIR . 'criteo.json');
define('MERGED_FILE',    DATA_DIR . 'merged.json');

// Auth
$apiKey = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';
// La suppression est appelée depuis le frontend sans clé — on l'autorise
// Si tu veux sécuriser, décommente :
// if ($apiKey !== API_KEY) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data || empty($data['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing id']);
    exit;
}

$id   = $data['id'];
$type = isset($data['type']) ? $data['type'] : 'favorite';

$deleted = false;

// Supprimer des favoris
$favorites = loadJson(FAVORITES_FILE, array());
foreach ($favorites as $key => $item) {
    if (isset($item['id']) && $item['id'] === $id) {
        unset($favorites[$key]);
        $deleted = true;
        break;
    }
}
if ($deleted) saveJson(FAVORITES_FILE, $favorites);

// Supprimer du Criteo si pas trouvé dans favoris
if (!$deleted) {
    $criteo = loadJson(CRITEO_FILE, array());
    foreach ($criteo as $key => $item) {
        if (isset($item['imageUrl']) && ($item['imageUrl'] === $id || (isset($item['id']) && $item['id'] === $id))) {
            unset($criteo[$key]);
            $deleted = true;
            break;
        }
    }
    if ($deleted) saveJson(CRITEO_FILE, $criteo);
}

// Rebuild merged.json
if ($deleted) {
    $favs   = array_values(loadJson(FAVORITES_FILE, array()));
    $crits  = array_values(loadJson(CRITEO_FILE, array()));
    foreach ($favs  as &$f) { $f['_type'] = 'favorite'; }
    foreach ($crits as &$c) { $c['_type'] = 'criteo'; }
    $merged = array_merge($favs, $crits);
    usort($merged, function($a, $b) {
        $va = isset($a['capturedAt']) ? $a['capturedAt'] : 0;
        $vb = isset($b['capturedAt']) ? $b['capturedAt'] : 0;
        return $vb - $va;
    });
    saveJson(MERGED_FILE, $merged);
}

echo json_encode(array('ok' => true, 'deleted' => $deleted, 'id' => $id));

function loadJson($file, $default) {
    if (!file_exists($file)) return $default;
    $decoded = json_decode(file_get_contents($file), true);
    return is_array($decoded) ? $decoded : $default;
}

function saveJson($file, $data) {
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($file, json_encode(array_values($data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}