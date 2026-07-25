<?php
// ============================================================
// ImmoAggregator — API delete.php
// Supprime une annonce des fichiers JSON
// ============================================================

require_once __DIR__ . '/analysis_types.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }

define('DATA_DIR',       __DIR__ . '/../data/');
define('FAVORITES_FILE', DATA_DIR . 'favorites.json');

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data || empty($data['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing id']);
    exit;
}

$id = (string)$data['id'];
if (!preg_match('/^[A-Za-z0-9_-]{1,180}$/', $id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid id']);
    exit;
}
$deleted = false;

// Supprimer des favoris
$favorites = loadJson(FAVORITES_FILE, array());
foreach ($favorites as $key => $item) {
    if (isset($item['id']) && (string)$item['id'] === $id) {
        unset($favorites[$key]);
        $deleted = true;
        break;
    }
}
if ($deleted) saveJson(FAVORITES_FILE, $favorites);

$deletedAnalysisFiles = deleteAnalysisFiles($id);

echo json_encode(array(
    'ok' => true,
    'deleted' => $deleted,
    'deletedAnalysisFiles' => $deletedAnalysisFiles,
    'id' => $id,
));

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

function deleteAnalysisFiles($id) {
    $files = array(DATA_DIR . 'analyses/jobs/' . $id . '.json');
    foreach (analysisTypes() as $type) {
        $files[] = DATA_DIR . 'analyses/' . $type . '/' . $id . '.json';
    }

    $deleted = 0;
    foreach ($files as $file) {
        if (!is_file($file)) continue;
        if (!unlink($file)) {
            http_response_code(500);
            echo json_encode(['error' => 'Unable to delete analysis data', 'id' => $id]);
            exit;
        }
        $deleted++;
    }
    return $deleted;
}
