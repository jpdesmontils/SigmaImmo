<?php
/** Comparables et positionnement de prix issus des mutations DVF. */
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/dvf_service.php';
require_once __DIR__ . '/../app/Database/bootstrap.php';
require_once __DIR__ . '/../app/Repositories/PropertyRepository.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) priceRespond(405, ['ok' => false, 'error' => 'Method not allowed']);

$id = isset($_GET['id']) ? (string)$_GET['id'] : '';
if (!preg_match('/^[A-Za-z0-9_-]{1,180}$/', $id)) priceRespond(400, ['ok' => false, 'error' => 'Identifiant invalide.']);
$logContext = ['request_id' => uniqid('dvf-', true), 'id' => $id, 'method' => $_SERVER['REQUEST_METHOD']];
appLog('app', 'dvf.request_started', $logContext);
$dataDirectory = __DIR__ . '/../data/';
$listing = (new PropertyRepository(sigma_db()))->find($id);
if (!$listing) {
    appLog('app', 'dvf.request_rejected', $logContext + ['step' => 'find_listing', 'reason' => 'listing_not_found']);
    priceRespond(404, ['ok' => false, 'error' => 'Annonce introuvable.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stored = dvfReadAnalysis($id, $dataDirectory);
        if ($stored && dvfAnalysisMatchesListing($stored, $listing)) {
            appLog('app', 'dvf.stored_analysis_returned', $logContext + ['captured_at' => isset($stored['captured_at']) ? $stored['captured_at'] : null]);
            priceRespond(200, ['ok' => true, 'stored' => true] + $stored['result']);
        }
    } catch (RuntimeException $error) {
        appLog('app', 'dvf.request_failed', $logContext + ['step' => 'read_analysis', 'error' => $error->getMessage()]);
        priceRespond(500, ['ok' => false, 'error' => $error->getMessage()]);
    }
}

try {
    $stored = dvfCreateAnalysis($id, $listing, $dataDirectory, $logContext);
    appLog('app', 'dvf.request_succeeded', $logContext + ['city_code' => $stored['result']['location']['city_code'], 'comparable_count' => count($stored['result']['transactions']), 'captured_at' => $stored['captured_at']]);
    priceRespond(200, ['ok' => true, 'stored' => true] + $stored['result']);
} catch (InvalidArgumentException $error) {
    appLog('app', 'dvf.request_failed', $logContext + ['error_type' => get_class($error), 'error' => $error->getMessage()]);
    priceRespond(422, ['ok' => false, 'error' => $error->getMessage()]);
} catch (RuntimeException $error) {
    appLog('app', 'dvf.request_failed', $logContext + ['error_type' => get_class($error), 'error' => $error->getMessage()]);
    priceRespond(503, ['ok' => false, 'error' => $error->getMessage()]);
}

function priceRespond($status, $payload) { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }
