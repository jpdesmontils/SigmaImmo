<?php
/** Comparables et positionnement de prix issus des mutations DVF. */
require_once __DIR__ . '/dvf_service.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') priceRespond(405, ['ok' => false, 'error' => 'Method not allowed']);

$id = isset($_GET['id']) ? (string)$_GET['id'] : '';
if (!preg_match('/^[A-Za-z0-9_-]{1,180}$/', $id)) priceRespond(400, ['ok' => false, 'error' => 'Identifiant invalide.']);
$favoritesFile = __DIR__ . '/../data/favorites.json';
$favorites = is_file($favoritesFile) ? json_decode(file_get_contents($favoritesFile), true) : [];
$listing = null;
foreach ((array)$favorites as $item) if (is_array($item) && (string)($item['id'] ?? '') === $id) { $listing = $item; break; }
if (!$listing) priceRespond(404, ['ok' => false, 'error' => 'Annonce introuvable.']);

$context = dvfListingContext($listing);
$missing = [];
if ($context['query'] === '' && ($context['latitude'] === null || $context['longitude'] === null)) $missing[] = 'localisation';
if ($context['surface'] <= 0) $missing[] = 'surface';
if ($missing) priceRespond(422, ['ok' => false, 'error' => 'Renseignez ' . (count($missing) === 2 ? 'la localisation et la surface' : 'la ' . $missing[0]) . ' du bien dans l’onglet Annonce pour rechercher des comparables.', 'missing' => $missing]);

try {
    $cacheKey = 'listing_v3_' . $id . '_' . substr(sha1(json_encode($context)), 0, 12);
    $result = dvfCache($cacheKey, 21600, function() use ($context) {
        $origin = dvfResolveLocation($context);
        if ($origin['city_code'] === '') throw new InvalidArgumentException('La commune de cette adresse n’a pas pu être identifiée.');
        $transactions = dvfNormalizeTransactions(dvfRowsForCommune($origin['city_code']), $origin);
        $selection = dvfSelectComparables($transactions, $context['type'], $context['surface'], 10);
        if (!$selection['items']) throw new RuntimeException('Aucune vente comparable de maison ou d’appartement n’a été trouvée dans cette commune.');
        return ['property' => ['asking_price' => $context['price'], 'surface' => $context['surface'], 'type' => dvfPropertyType($context['type'])], 'location' => $origin, 'perimeter' => $selection['perimeter'], 'transactions' => $selection['items'], 'summary' => dvfSummary($selection['items'], $context['price'], $context['surface'])];
    });
    priceRespond(200, ['ok' => true, 'source' => ['name' => 'DVF — DGFiP / data.gouv.fr', 'url' => 'https://www.data.gouv.fr/fr/datasets/demandes-de-valeurs-foncieres-geolocalisees/', 'limitations' => 'Les données DVF décrivent des mutations enregistrées et ne reflètent ni l’état intérieur, ni les travaux, ni les conditions particulières de chaque vente.'], 'generated_at' => gmdate('c')] + $result);
} catch (InvalidArgumentException $error) {
    priceRespond(422, ['ok' => false, 'error' => $error->getMessage()]);
} catch (RuntimeException $error) {
    priceRespond(503, ['ok' => false, 'error' => $error->getMessage()]);
}

function priceRespond($status, $payload) { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }
