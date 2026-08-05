<?php
/** Lecture et mise à jour de la fiche mutualisée d'une annonce. */
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/analysis_types.php';
require_once __DIR__ . '/../app/Database/bootstrap.php';
require_once __DIR__ . '/../app/Repositories/PropertyRepository.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

define('DATA_DIR', __DIR__ . '/../data/');
define('SETTINGS_FILE', DATA_DIR . 'settings.json');
$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
if (!preg_match('/^[A-Za-z0-9_-]{1,180}$/', $id)) respond(400, ['ok' => false, 'error' => 'Identifiant invalide.']);

$propertyRepo = new PropertyRepository(sigma_db());
$listing = $propertyRepo->find($id);
if (!$listing) respond(404, ['ok' => false, 'error' => 'Annonce introuvable.']);

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) respond(400, ['ok' => false, 'error' => 'Corps JSON invalide.']);
    // Les compléments enrichissent les champs canoniques : aucun second objet
    // de paramètres n'est créé et une synchronisation conserve ces valeurs.
    $fields = ['address' => 'text', 'location' => 'text', 'price' => 'number', 'surface' => 'number', 'rooms' => 'text', 'terrain' => 'number', 'dpe' => 'energy', 'ges' => 'energy', 'primaryResidenceCity' => 'required_text', 'notes' => 'notes', 'visitAt' => 'datetime', 'agentName' => 'text', 'agentPhone' => 'phone', 'agentEmail' => 'email'];
    $previousAddress = isset($listing['address']) ? trim((string)$listing['address']) : '';
    foreach ($fields as $field => $kind) if (array_key_exists($field, $payload)) {
        $value = $payload[$field];
        if ($kind === 'number') $listing[$field] = $value === '' || $value === null ? null : max(0, (float)$value);
        elseif ($kind === 'energy') {
            $energy = strtoupper(trim((string)$value));
            if (!preg_match('/^[A-G]$/', $energy)) respond(400, ['ok' => false, 'error' => 'La note énergétique doit être comprise entre A et G.']);
            $listing[$field] = $energy;
        } elseif ($kind === 'datetime') {
            $date = trim((string)$value);
            if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $date)) respond(400, ['ok' => false, 'error' => 'La date de visite est invalide.']);
            $listing[$field] = $date;
        } elseif ($kind === 'required_text') {
            $text = mb_substr(trim(strip_tags((string)$value)), 0, 120);
            if ($text === '') respond(400, ['ok' => false, 'error' => 'La ville de résidence principale est obligatoire.']);
            $listing[$field] = $text;
        } elseif ($kind === 'email') {
            $email = mb_substr(trim((string)$value), 0, 254);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) respond(400, ['ok' => false, 'error' => 'L’adresse e-mail de l’agent est invalide.']);
            $listing[$field] = $email;
        } elseif ($kind === 'phone') {
            $phone = mb_substr(trim(strip_tags((string)$value)), 0, 40);
            if ($phone !== '' && !preg_match('/^[0-9+().\s-]+$/', $phone)) respond(400, ['ok' => false, 'error' => 'Le téléphone de l’agent est invalide.']);
            $listing[$field] = $phone;
        } elseif ($kind === 'notes') $listing[$field] = mb_substr(trim(strip_tags((string)$value)), 0, 10000);
        else $listing[$field] = mb_substr(trim(strip_tags((string)$value)), 0, 300);
    }
    $listing['updatedAt'] = time() * 1000;
    $propertyRepo->updateFields($id, $listing);
    if (array_key_exists('address', $payload) && $previousAddress !== (isset($listing['address']) ? $listing['address'] : '')) {
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

$settings = readSettings();
if (!isset($listing['primaryResidenceCity']) || trim((string)$listing['primaryResidenceCity']) === '') $listing['primaryResidenceCity'] = $settings['primaryResidenceCity'];
$summaries = analysisSummaries(DATA_DIR, $id);
$jobPath = DATA_DIR . 'analyses/jobs/' . $id . '.json';
$job = readJson($jobPath, null);
if ($job) expireStaleJob($jobPath, $job);
respond(200, ['ok' => true, 'listing' => $listing, 'settings' => $settings, 'analyses' => $summaries, 'job' => $job, 'requirements' => analysisRequirements($listing)]);

function analysisRequirements($listing) {
    // Ces champs sont les données minimales effectivement nécessaires aux calculs
    // demandés dans les prompts, y compris les variables explicites injectées.
    $definitions = [
        'price' => ['label' => 'Prix du bien', 'type' => 'number', 'suffix' => '€'],
        'surface' => ['label' => 'Surface habitable', 'type' => 'number', 'suffix' => 'm²'],
        'location' => ['label' => 'Commune ou localisation', 'type' => 'text'],
        'dpe' => ['label' => 'DPE', 'type' => 'text', 'pattern' => '[A-Ga-g]', 'maxlength' => '1'],
        'ges' => ['label' => 'GES', 'type' => 'text', 'pattern' => '[A-Ga-g]', 'maxlength' => '1'],
        'primaryResidenceCity' => ['label' => 'Ville de résidence principale', 'type' => 'text'],
    ];
    $result = [];
    foreach (analysisTypes() as $type) {
        $missing = [];
        foreach ($definitions as $field => $definition) if (!isset($listing[$field]) || $listing[$field] === '' || $listing[$field] === null) $missing[] = ['field' => $field] + $definition;
        $variables = ['annonce_complete', 'dpe', 'ges'];
        if ($type === 'patrimonial') $variables = array_merge($variables, ['ville_residence_principale', 'analyse_trajet', 'analyse_prix']);
        $result[$type] = ['promptVariables' => $variables, 'missing' => $missing];
    }
    return $result;
}
function readSettings() {
    $settings = readJson(SETTINGS_FILE, []);
    $city = isset($settings['primaryResidenceCity']) ? mb_substr(trim(strip_tags((string)$settings['primaryResidenceCity'])), 0, 120) : '';
    if ($city === '') $city = 'Paris';
    $settings['primaryResidenceCity'] = $city;
    return $settings;
}
function readJson($path, $default) { if (!is_file($path)) return $default; $value = json_decode(file_get_contents($path), true); return is_array($value) ? $value : $default; }
function respond($status, $payload) { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }
