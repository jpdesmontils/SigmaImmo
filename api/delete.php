<?php
// ============================================================
// ImmoAggregator — API delete.php
// Supprime une annonce des fichiers JSON
// ============================================================

require_once __DIR__ . '/analysis_types.php';
require_once __DIR__ . '/../app/Database/bootstrap.php';
require_once __DIR__ . '/../app/Repositories/PropertyRepository.php';

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
$deleted = (new PropertyRepository(sigma_db()))->softDelete($id);

// Les anciens fichiers JSON restent en sauvegarde temporaire; la suppression critique est SQLite.
$deletedAnalysisFiles = deleteAnalysisFiles($id);

echo json_encode(array(
    'ok' => true,
    'deleted' => $deleted,
    'deletedAnalysisFiles' => $deletedAnalysisFiles,
    'id' => $id,
));

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
