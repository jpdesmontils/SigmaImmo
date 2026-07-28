<?php
require_once __DIR__ . '/../api/dvf_service.php';

function dvfAssert($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nAttendu: " . var_export($expected, true) . "\nReçu: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$origin = ['latitude' => 48.8566, 'longitude' => 2.3522];
$rows = [
    ['id_mutation' => 'vente-1', 'date_mutation' => '2025-06-02', 'nature_mutation' => 'Vente', 'valeur_fonciere' => '300000', 'type_local' => 'Appartement', 'surface_reelle_bati' => '40', 'nombre_pieces_principales' => '2', 'adresse_numero' => '12', 'adresse_nom_voie' => 'RUE TEST', 'latitude' => 48.8567, 'longitude' => 2.3523],
    ['id_mutation' => 'vente-1', 'date_mutation' => '2025-06-02', 'nature_mutation' => 'Vente', 'valeur_fonciere' => '300000', 'type_local' => 'Appartement', 'surface_reelle_bati' => '20', 'nombre_pieces_principales' => '1', 'adresse_numero' => '12', 'adresse_nom_voie' => 'RUE TEST', 'latitude' => 48.8567, 'longitude' => 2.3523],
    ['id_mutation' => 'vente-2', 'date_mutation' => '2025-04-01', 'nature_mutation' => 'Vente', 'valeur_fonciere' => '270000', 'type_local' => 'Maison', 'surface_reelle_bati' => '60', 'latitude' => 48.87, 'longitude' => 2.36],
    ['id_mutation' => 'don-1', 'date_mutation' => '2025-05-01', 'nature_mutation' => 'Donation', 'valeur_fonciere' => '280000', 'type_local' => 'Appartement', 'surface_reelle_bati' => '60'],
];

$transactions = dvfNormalizeTransactions($rows, $origin);
dvfAssert(2, count($transactions), 'Les ventes doivent être regroupées par mutation et les donations exclues.');
dvfAssert(60.0, $transactions[0]['surface'], 'Les surfaces des lots d’une mutation doivent être additionnées.');
dvfAssert(5000.0, $transactions[0]['price_per_sqm'], 'Le prix au mètre carré doit utiliser la mutation regroupée.');

$selection = dvfSelectComparables($transactions, 'Appartement ancien', 60, 10);
dvfAssert(1, count($selection['items']), 'Le type du bien doit écarter les maisons.');
dvfAssert('commune, même type de bien', $selection['perimeter'], 'Le périmètre doit signaler le dernier élargissement nécessaire.');

$summary = dvfSummary([
    ['price_per_sqm' => 4000], ['price_per_sqm' => 5000], ['price_per_sqm' => 6000],
], 360000, 60);
dvfAssert(5000.0, $summary['median_price_per_sqm'], 'La médiane doit être calculée sur les comparables.');
dvfAssert(20.0, $summary['asking_gap_percent'], 'L’écart au prix demandé doit être exprimé en pourcentage.');
dvfAssert(300000.0, $summary['estimated_value'], 'La valeur centrale doit appliquer la médiane à la surface.');

$context = dvfListingContext([
    'title' => 'Appartement avec balcon', 'surface' => null, 'surfaceText' => 'Surface 72,5 m²',
    'price' => null, 'priceText' => '315 000 €', 'location' => '', 'coords' => ['lat' => '48.8', 'lng' => '2.3'],
]);
dvfAssert(72.5, $context['surface'], 'La surface textuelle de favorites.json doit servir de repli.');
dvfAssert(315000.0, $context['price'], 'Le prix textuel de favorites.json doit servir de repli.');
dvfAssert(48.8, $context['latitude'], 'Les coordonnées déjà enregistrées doivent éviter d’exiger une adresse.');
dvfAssert('', $context['query'], 'Une adresse vide doit être acceptée lorsque des coordonnées existent.');

$dataDirectory = sys_get_temp_dir() . '/sigma-immo-prix-' . uniqid('', true) . '/';
$analysis = ['version' => 1, 'id' => 'bien-test', 'api_data' => ['dvf_rows' => [['id_mutation' => 'vente-1']]], 'result' => ['summary' => ['median_price_per_sqm' => 5000]]];
dvfWriteAnalysis('bien-test', $dataDirectory, $analysis);
dvfAssert($analysis, dvfReadAnalysis('bien-test', $dataDirectory), 'L’analyse Prix complète doit être relue depuis data/analyses/prix/<id>.json.');
dvfAssert(true, is_file($dataDirectory . 'analyses/prix/bien-test.json'), 'Le fichier doit respecter l’arborescence des analyses Prix.');
unlink($dataDirectory . 'analyses/prix/bien-test.json');
rmdir($dataDirectory . 'analyses/prix'); rmdir($dataDirectory . 'analyses'); rmdir($dataDirectory);

echo "dvf_service_test: OK\n";
