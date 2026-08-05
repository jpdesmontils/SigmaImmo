<?php
/** Paramètres serveur partagés par défaut. */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
define('SETTINGS_FILE', __DIR__ . '/../data/settings.json');
$settings = readSettings();
if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) respond(400, ['ok' => false, 'error' => 'Corps JSON invalide.']);
    if (array_key_exists('primaryResidenceCity', $payload)) {
        $city = sanitizeCity($payload['primaryResidenceCity']);
        if ($city === '') respond(400, ['ok' => false, 'error' => 'La ville de résidence principale est obligatoire.']);
        $settings['primaryResidenceCity'] = $city;
    }
    writeSettings($settings);
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'PATCH') respond(405, ['ok' => false, 'error' => 'Method not allowed']);
respond(200, ['ok' => true, 'settings' => $settings]);
function readSettings() {
    $defaults = ['primaryResidenceCity' => 'Paris'];
    if (!is_file(SETTINGS_FILE)) return $defaults;
    $value = json_decode(file_get_contents(SETTINGS_FILE), true);
    if (!is_array($value)) return $defaults;
    $city = sanitizeCity(isset($value['primaryResidenceCity']) ? $value['primaryResidenceCity'] : '');
    $value['primaryResidenceCity'] = $city !== '' ? $city : $defaults['primaryResidenceCity'];
    return $value + $defaults;
}
function writeSettings($settings) {
    $dir = dirname(SETTINGS_FILE);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) respond(500, ['ok' => false, 'error' => 'Répertoire de paramètres inaccessible.']);
    if (file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) respond(500, ['ok' => false, 'error' => 'Écriture des paramètres impossible.']);
}
function sanitizeCity($value) { return mb_substr(trim(strip_tags((string)$value)), 0, 120); }
function respond($status, $payload) { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }
