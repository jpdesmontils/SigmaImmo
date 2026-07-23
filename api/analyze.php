<?php
/** Lance et suit les analyses OpenAI sans bloquer la requête HTTP. */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

define('DATA_DIR', __DIR__ . '/../data/');
define('FAVORITES_FILE', DATA_DIR . 'favorites.json');
define('JOBS_DIR', DATA_DIR . 'analyses/jobs/');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? $_GET['id'] : '';
    if (!validId($id)) jsonError(400, 'Identifiant d’annonce invalide.');
    $job = readJson(jobPath($id));
    $files = analysisFiles($id);
    echo json_encode(['ok' => true, 'id' => $id, 'job' => $job, 'analyses' => $files], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError(405, 'Method not allowed');

$payload = json_decode(file_get_contents('php://input'), true);
$id = isset($payload['id']) ? (string)$payload['id'] : '';
$type = isset($payload['type']) ? (string)$payload['type'] : '';
if (!validId($id) || !in_array($type, ['locatif', 'mdb'], true)) jsonError(400, 'Paramètres d’analyse invalides.');

$favorite = findFavorite($id);
if (!$favorite) jsonError(404, 'Annonce favorite introuvable.');
if (($favorite['selection'] ?? '') !== 'invest') jsonError(403, 'Seules les annonces taguées Invest peuvent être analysées.');
if (!is_dir(JOBS_DIR) && !mkdir(JOBS_DIR, 0755, true) && !is_dir(JOBS_DIR)) jsonError(500, 'Création du répertoire de tâches impossible.');

$path = jobPath($id);
$current = readJson($path);
if (($current['status'] ?? '') === 'running') jsonError(409, 'Une analyse est déjà en cours pour cette annonce.');

$job = ['id' => $id, 'type' => $type, 'status' => 'running', 'started_at' => gmdate('c'), 'finished_at' => null, 'error' => null];
// Le verrou exclusif évite que deux requêtes simultanées démarrent deux workers.
$handle = @fopen($path, 'x');
if ($handle === false) {
    $current = readJson($path);
    if (($current['status'] ?? '') === 'running') jsonError(409, 'Une analyse est déjà en cours pour cette annonce.');
    if (!@file_put_contents($path, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX)) jsonError(500, 'Écriture de la tâche impossible.');
} else {
    fwrite($handle, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fclose($handle);
}

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/analyze_worker.php') . ' ' . escapeshellarg($id) . ' ' . escapeshellarg($type) . ' > /dev/null 2>&1 &';
@exec($command, $output, $exitCode);
if ($exitCode !== 0) {
    $job['status'] = 'failed'; $job['finished_at'] = gmdate('c'); $job['error'] = 'Impossible de démarrer le traitement en arrière-plan.';
    writeJson($path, $job);
    jsonError(500, $job['error']);
}
echo json_encode(['ok' => true, 'job' => $job], JSON_UNESCAPED_UNICODE);

function validId($id) { return is_string($id) && preg_match('/^[A-Za-z0-9_-]{1,180}$/', $id); }
function jobPath($id) { return JOBS_DIR . $id . '.json'; }
function analysisFiles($id) { return ['locatif' => is_file(DATA_DIR . 'analyses/locatif/' . $id . '.json'), 'mdb' => is_file(DATA_DIR . 'analyses/mdb/' . $id . '.json')]; }
function findFavorite($id) { foreach (readJson(FAVORITES_FILE, []) as $item) if (isset($item['id']) && (string)$item['id'] === $id) return $item; return null; }
function readJson($path, $default = null) { if (!is_file($path)) return $default; $value = json_decode(file_get_contents($path), true); return is_array($value) ? $value : $default; }
function writeJson($path, $value) { file_put_contents($path, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX); }
function jsonError($status, $message) { http_response_code($status); echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE); exit; }
