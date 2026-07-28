<?php
/** Comparables et positionnement de prix issus des mutations DVF. */
require_once __DIR__ . '/dvf_service.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) priceRespond(405, ['ok' => false, 'error' => 'Method not allowed']);

$id = isset($_GET['id']) ? (string)$_GET['id'] : '';
if (!preg_match('/^[A-Za-z0-9_-]{1,180}$/', $id)) priceRespond(400, ['ok' => false, 'error' => 'Identifiant invalide.']);
$favoritesFile = __DIR__ . '/../data/favorites.json';
$dataDirectory = __DIR__ . '/../data/';
$favorites = is_file($favoritesFile) ? json_decode(file_get_contents($favoritesFile), true) : [];
$listing = null;
foreach ((array)$favorites as $item) if (is_array($item) && (string)($item['id'] ?? '') === $id) { $listing = $item; break; }
if (!$listing) priceRespond(404, ['ok' => false, 'error' => 'Annonce introuvable.']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stored = dvfReadAnalysis($id, $dataDirectory);
        if ($stored) priceRespond(200, ['ok' => true, 'stored' => true] + $stored['result']);
    } catch (RuntimeException $error) {
        priceRespond(500, ['ok' => false, 'error' => $error->getMessage()]);
    }
}

$context = dvfListingContext($listing);
$missing = [];
if ($context['query'] === '' && ($context['latitude'] === null || $context['longitude'] === null)) $missing[] = 'localisation';
if ($context['surface'] <= 0) $missing[] = 'surface';
if ($missing) priceRespond(422, ['ok' => false, 'error' => 'Renseignez ' . (count($missing) === 2 ? 'la localisation et la surface' : 'la ' . $missing[0]) . ' du bien dans l’onglet Annonce pour rechercher des comparables.', 'missing' => $missing]);

try {
    $origin = dvfResolveLocation($context);
    if ($origin['city_code'] === '') throw new InvalidArgumentException('La commune de cette adresse n’a pas pu être identifiée.');
    $rows = dvfRowsForCommune($origin['city_code']);
    $transactions = dvfNormalizeTransactions($rows, $origin);
    $selection = dvfSelectComparables($transactions, $context['type'], $context['surface'], 10);
    if (!$selection['items']) throw new RuntimeException('Aucune vente comparable de maison ou d’appartement n’a été trouvée dans cette commune.');
    $source = ['name' => 'DVF — DGFiP / data.gouv.fr', 'url' => 'https://www.data.gouv.fr/fr/datasets/demandes-de-valeurs-foncieres-geolocalisees/', 'limitations' => 'Les données DVF décrivent des mutations enregistrées et ne reflètent ni l’état intérieur, ni les travaux, ni les conditions particulières de chaque vente.'];
    $capturedAt = gmdate('c');
    $result = ['source' => $source, 'captured_at' => $capturedAt, 'property' => ['asking_price' => $context['price'], 'surface' => $context['surface'], 'type' => dvfPropertyType($context['type'])], 'location' => $origin, 'perimeter' => $selection['perimeter'], 'transactions' => $selection['items'], 'summary' => dvfSummary($selection['items'], $context['price'], $context['surface'])];
    $stored = ['version' => 1, 'id' => $id, 'captured_at' => $capturedAt, 'input' => $context, 'api_data' => ['geocoding' => $origin, 'dvf_rows' => $rows], 'result' => $result];
    dvfWriteAnalysis($id, $dataDirectory, $stored);
    priceRespond(200, ['ok' => true, 'stored' => true] + $stored['result']);
} catch (InvalidArgumentException $error) {
    priceRespond(422, ['ok' => false, 'error' => $error->getMessage()]);
} catch (RuntimeException $error) {
    priceRespond(503, ['ok' => false, 'error' => $error->getMessage()]);
}

function priceRespond($status, $payload) { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }
