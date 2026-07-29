<?php
/** Lecture et mise à jour de la fiche mutualisée d'une annonce. */
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/analysis_types.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

define('DATA_DIR', __DIR__ . '/../data/');
define('FAVORITES_FILE', DATA_DIR . 'favorites.json');
$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
if (!preg_match('/^[A-Za-z0-9_-]{1,180}$/', $id)) respond(400, ['ok' => false, 'error' => 'Identifiant invalide.']);

$favorites = readJson(FAVORITES_FILE, []);
$key = null;
foreach ($favorites as $candidateKey => $item) if (is_array($item) && (string)($item['id'] ?? '') === $id) { $key = $candidateKey; break; }
if ($key === null) respond(404, ['ok' => false, 'error' => 'Annonce introuvable.']);

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) respond(400, ['ok' => false, 'error' => 'Corps JSON invalide.']);
    // Les compléments enrichissent les champs canoniques : aucun second objet
    // de paramètres n'est créé et une synchronisation conserve ces valeurs.
    $fields = ['address' => 'text', 'location' => 'text', 'price' => 'number', 'surface' => 'number', 'rooms' => 'text', 'terrain' => 'number', 'dpe' => 'energy', 'ges' => 'energy'];
    $previousAddress = isset($favorites[$key]['address']) ? trim((string)$favorites[$key]['address']) : '';
    foreach ($fields as $field => $kind) if (array_key_exists($field, $payload)) {
        $value = $payload[$field];
        if ($kind === 'number') $favorites[$key][$field] = $value === '' || $value === null ? null : max(0, (float)$value);
        elseif ($kind === 'energy') {
            $energy = strtoupper(trim((string)$value));
            if (!preg_match('/^[A-G]$/', $energy)) respond(400, ['ok' => false, 'error' => 'La note énergétique doit être comprise entre A et G.']);
            $favorites[$key][$field] = $energy;
        } else $favorites[$key][$field] = mb_substr(trim(strip_tags((string)$value)), 0, 300);
    }
    $favorites[$key]['updatedAt'] = time() * 1000;
    writeJson(FAVORITES_FILE, $favorites);
    if (array_key_exists('address', $payload) && $previousAddress !== (isset($favorites[$key]['address']) ? $favorites[$key]['address'] : '')) {
        $priceAnalysis = DATA_DIR . 'analyses/prix/' . $id . '.json';
        if (is_file($priceAnalysis)) @unlink($priceAnalysis);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $type = isset($_GET['type']) ? (string) $_GET['type'] : '';
    if (!validAnalysisType($type)) respond(400, ['ok' => false, 'error' => 'Type d’analyse invalide.']);
    $file = DATA_DIR . 'analyses/' . $type . '/' . $id . '.json';
    $deleted = is_file($file) ? unlink($file) : false;
    $jobFile = DATA_DIR . 'analyses/jobs/' . $id . '.json';
    $job = readJson($jobFile, []);
    if (($job['type'] ?? '') === $type && !in_array($job['status'] ?? '', ['queued', 'running'], true)) @unlink($jobFile);
    respond(200, ['ok' => true, 'deleted' => $deleted, 'type' => $type]);
}
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'PATCH'], true)) respond(405, ['ok' => false, 'error' => 'Method not allowed']);

$listing = $favorites[$key];
$summaries = analysisSummaries(DATA_DIR, $id);
$jobPath = DATA_DIR . 'analyses/jobs/' . $id . '.json';
$job = readJson($jobPath, null);
if ($job) expireStaleJob($jobPath, $job);
respond(200, ['ok' => true, 'listing' => $listing, 'analyses' => $summaries, 'job' => $job, 'requirements' => analysisRequirements($listing)]);

function analysisRequirements($listing) {
    // Les trois prompts ne reçoivent qu'`annonce_complete` (le trajet patrimonial
    // est calculé en interne). Ces champs sont les données minimales effectivement
    // nécessaires aux calculs demandés dans les prompts.
    $definitions = [
        'price' => ['label' => 'Prix du bien', 'type' => 'number', 'suffix' => '€'],
        'surface' => ['label' => 'Surface habitable', 'type' => 'number', 'suffix' => 'm²'],
        'location' => ['label' => 'Commune ou localisation', 'type' => 'text'],
    ];
    $result = [];
    foreach (analysisTypes() as $type) {
        $missing = [];
        foreach ($definitions as $field => $definition) if (!isset($listing[$field]) || $listing[$field] === '' || $listing[$field] === null) $missing[] = ['field' => $field] + $definition;
        $result[$type] = ['promptVariables' => $type === 'patrimonial' ? ['annonce_complete', 'analyse_trajet', 'analyse_prix'] : ['annonce_complete'], 'missing' => $missing];
    }
    return $result;
}
function readJson($path, $default) { if (!is_file($path)) return $default; $value = json_decode(file_get_contents($path), true); return is_array($value) ? $value : $default; }
function writeJson($path, $value) { if (file_put_contents($path, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) respond(500, ['ok' => false, 'error' => 'Écriture impossible.']); }
function respond($status, $payload) { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }
