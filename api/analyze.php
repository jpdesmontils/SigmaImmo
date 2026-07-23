<?php
/** Lance et suit les analyses OpenAI sans bloquer la requête HTTP. */
require_once __DIR__ . '/logger.php';
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
    $path = jobPath($id);
    $job = readJson($path);
    expireStaleJob($path, $job);
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
expireStaleJob($path, $current);
if (jobIsActive($current)) { aiLog('analysis.start_rejected_active', ['id' => $id, 'type' => $type, 'status' => $current['status']]); jsonError(409, 'Une analyse est déjà en attente ou en cours pour cette annonce.'); }

$job = ['id' => $id, 'type' => $type, 'status' => 'queued', 'queued_at' => gmdate('c'), 'started_at' => null, 'lease_expires_at' => null, 'finished_at' => null, 'error' => null];
// Le verrou exclusif évite que deux requêtes simultanées démarrent deux workers.
$handle = @fopen($path, 'x');
if ($handle === false) {
    $current = readJson($path);
    expireStaleJob($path, $current);
    if (jobIsActive($current)) { aiLog('analysis.start_rejected_active', ['id' => $id, 'type' => $type, 'status' => $current['status']]); jsonError(409, 'Une analyse est déjà en attente ou en cours pour cette annonce.'); }
    if (!@file_put_contents($path, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX)) jsonError(500, 'Écriture de la tâche impossible.');
} else {
    fwrite($handle, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fclose($handle);
}

aiLog('analysis.job_queued', ['id' => $id, 'type' => $type]);

$launcherLog = __DIR__ . '/../log/ai/worker-launch.log';
if (!function_exists('exec')) {
    failJob($path, $job, 'Le serveur PHP ne permet pas de lancer le worker en arrière-plan (fonction exec désactivée).');
}
if (!is_dir(dirname($launcherLog)) && !@mkdir(dirname($launcherLog), 0755, true) && !is_dir(dirname($launcherLog))) {
    failJob($path, $job, 'Répertoire de logs du worker inaccessible.');
}
$phpBinary = PHP_BINARY ?: '/usr/bin/php';
if (!is_executable($phpBinary)) {
    failJob($path, $job, 'Le binaire PHP CLI est introuvable ou non exécutable : ' . $phpBinary);
}
$command = escapeshellarg($phpBinary) . ' ' . escapeshellarg(__DIR__ . '/analyze_worker.php') . ' ' . escapeshellarg($id) . ' ' . escapeshellarg($type) . ' >> ' . escapeshellarg($launcherLog) . ' 2>&1 &';
@exec($command, $output, $exitCode);
if ($exitCode !== 0) {
    failJob($path, $job, 'Impossible de démarrer le traitement en arrière-plan.');
    aiLog('analysis.worker_start_failed', ['id' => $id, 'type' => $type, 'exit_code' => $exitCode]);
}
aiLog('analysis.worker_spawned', ['id' => $id, 'type' => $type, 'php_binary' => $phpBinary, 'launcher_log' => $launcherLog]);
echo json_encode(['ok' => true, 'job' => $job], JSON_UNESCAPED_UNICODE);

function validId($id) { return is_string($id) && preg_match('/^[A-Za-z0-9_-]{1,180}$/', $id); }
function jobPath($id) { return JOBS_DIR . $id . '.json'; }
function analysisFiles($id) { return ['locatif' => is_file(DATA_DIR . 'analyses/locatif/' . $id . '.json'), 'mdb' => is_file(DATA_DIR . 'analyses/mdb/' . $id . '.json')]; }
function findFavorite($id) { foreach (readJson(FAVORITES_FILE, []) as $item) if (isset($item['id']) && (string)$item['id'] === $id) return $item; return null; }
function readJson($path, $default = null) { if (!is_file($path)) return $default; $value = json_decode(file_get_contents($path), true); return is_array($value) ? $value : $default; }
function writeJson($path, $value) { file_put_contents($path, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX); }
function jobIsActive($job) { if (($job['status'] ?? '') === 'queued') return true; if (($job['status'] ?? '') !== 'running') return false; $expiresAt = isset($job['lease_expires_at']) ? strtotime($job['lease_expires_at']) : false; return $expiresAt !== false && $expiresAt > time(); }
function expireStaleJob($path, &$job) { if (($job['status'] ?? '') !== 'running' || jobIsActive($job)) return; $job['status'] = 'failed'; $job['finished_at'] = gmdate('c'); $job['error'] = 'Le worker ne répond plus ou le délai de réponse du LLM a expiré.'; writeJson($path, $job); aiLog('analysis.worker_expired', ['id' => $job['id'] ?? null, 'type' => $job['type'] ?? null]); }
function failJob($path, $job, $error) { $job['status'] = 'failed'; $job['finished_at'] = gmdate('c'); $job['error'] = $error; writeJson($path, $job); aiLog('analysis.worker_start_failed', ['id' => $job['id'], 'type' => $job['type'], 'error' => $error]); jsonError(500, $error); }
function jsonError($status, $message) { http_response_code($status); echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE); exit; }
