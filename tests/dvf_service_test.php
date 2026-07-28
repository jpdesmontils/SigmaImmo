<?php
require_once __DIR__ . '/../api/dvf_service.php';

$GLOBALS['dvfTestCount'] = 0;

function dvfAssert($expected, $actual, $message) {
    $GLOBALS['dvfTestCount']++;
    if ($expected !== $actual) {
        fwrite(STDERR, "[ECHEC] " . $message . "\nAttendu: " . var_export($expected, true) . "\nReçu: " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "  [OK] " . $message . "\n";
}

echo "=== dvf_service_test ===\n";

echo "-- Regroupement des mutations et calcul du prix au m²\n";
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

echo "-- Sélection des comparables par périmètre\n";
$selection = dvfSelectComparables($transactions, 'Appartement ancien', 60, 10);
dvfAssert(1, count($selection['items']), 'Le type du bien doit écarter les maisons.');
dvfAssert('commune, même type de bien', $selection['perimeter'], 'Le périmètre doit signaler le dernier élargissement nécessaire.');

echo "-- Synthèse statistique (médiane, écart au prix demandé, estimation)\n";
$summary = dvfSummary([
    ['price_per_sqm' => 4000], ['price_per_sqm' => 5000], ['price_per_sqm' => 6000],
], 360000, 60);
dvfAssert(5000.0, $summary['median_price_per_sqm'], 'La médiane doit être calculée sur les comparables.');
dvfAssert(20.0, $summary['asking_gap_percent'], 'L’écart au prix demandé doit être exprimé en pourcentage.');
dvfAssert(300000.0, $summary['estimated_value'], 'La valeur centrale doit appliquer la médiane à la surface.');

echo "-- Extraction du contexte d’une annonce favorites.json (repli texte)\n";
$context = dvfListingContext([
    'title' => 'Appartement avec balcon', 'surface' => null, 'surfaceText' => 'Surface 72,5 m²',
    'price' => null, 'priceText' => '315 000 €', 'location' => '', 'coords' => ['lat' => '48.8', 'lng' => '2.3'],
]);
dvfAssert(72.5, $context['surface'], 'La surface textuelle de favorites.json doit servir de repli.');
dvfAssert(315000.0, $context['price'], 'Le prix textuel de favorites.json doit servir de repli.');
dvfAssert(48.8, $context['latitude'], 'Les coordonnées déjà enregistrées doivent éviter d’exiger une adresse.');
dvfAssert('', $context['query'], 'Une adresse vide doit être acceptée lorsque des coordonnées existent.');

echo "-- Persistance de l’analyse Prix (data/analyses/prix/<id>.json)\n";
$dataDirectory = sys_get_temp_dir() . '/sigma-immo-prix-' . uniqid('', true) . '/';
$analysis = ['version' => 1, 'id' => 'bien-test', 'api_data' => ['dvf_rows' => [['id_mutation' => 'vente-1']]], 'result' => ['summary' => ['median_price_per_sqm' => 5000]]];
dvfWriteAnalysis('bien-test', $dataDirectory, $analysis);
dvfAssert($analysis, dvfReadAnalysis('bien-test', $dataDirectory), 'L’analyse Prix complète doit être relue depuis data/analyses/prix/<id>.json.');
dvfAssert(true, is_file($dataDirectory . 'analyses/prix/bien-test.json'), 'Le fichier doit respecter l’arborescence des analyses Prix.');
unlink($dataDirectory . 'analyses/prix/bien-test.json');
rmdir($dataDirectory . 'analyses/prix'); rmdir($dataDirectory . 'analyses'); rmdir($dataDirectory);

echo "-- Résolution du code département depuis le code commune INSEE (correctif geo-dvf)\n";
dvfAssert('11', dvfDepartmentCode('11106'), 'Coursan (11106) doit être rattachée au département 11 (Aude).');
dvfAssert('75', dvfDepartmentCode('75056'), 'Paris (75056) doit être rattachée au département 75.');
dvfAssert('2A', dvfDepartmentCode('2a004'), 'Une commune de Corse-du-Sud doit être rattachée au département 2A (insensible à la casse).');
dvfAssert('972', dvfDepartmentCode('97209'), 'Fort-de-France (97209) doit être rattachée au département 972 (DOM sur 3 chiffres).');
dvfAssert('', dvfDepartmentCode('1234'), 'Un code commune trop court doit être rejeté plutôt que tronqué silencieusement.');
dvfAssert('', dvfDepartmentCode(''), 'Un code commune vide ne doit pas produire de faux département.');

echo "-- Construction de l’URL geo-dvf (fichier CSV statique par commune et par année)\n";
dvfAssert(
    'https://files.data.gouv.fr/geo-dvf/latest/csv/2025/communes/11/11106.csv',
    dvfCommuneCsvUrl('11106', '11', 2025),
    'L’URL doit pointer vers le fichier CSV non compressé par commune de geo-dvf, pas vers l’API tabulaire du dataset.'
);

echo "-- Analyse CSV d’un flux communal geo-dvf simulé\n";
$csvSample = "id_mutation,date_mutation,nature_mutation,valeur_fonciere,type_local,surface_reelle_bati,nombre_pieces_principales,adresse_nom_voie,latitude,longitude\n"
    . "2024-1,2024-03-12,Vente,287000,Maison,117,5,\"RUE DE LA, MAIRIE\",43.2347407,3.0585293\n";
$parsedRows = dvfParseCsv($csvSample);
dvfAssert(1, count($parsedRows), 'Une ligne de données doit produire une transaction.');
dvfAssert('287000', $parsedRows[0]['valeur_fonciere'], 'La colonne valeur_fonciere doit être lue depuis l’en-tête CSV.');
dvfAssert('RUE DE LA, MAIRIE', $parsedRows[0]['adresse_nom_voie'], 'Une virgule entre guillemets ne doit pas casser le parsing CSV.');

echo "-- Chargement de quatre millésimes complets, indépendamment du nombre de lignes\n";
$loadedYears = [];
$historicalRows = dvfLoadCommuneYears(2025, function($year) use (&$loadedYears) {
    $loadedYears[] = $year;
    $count = $year === 2025 ? 401 : 1;
    return array_fill(0, $count, ['date_mutation' => (string)$year]);
});
dvfAssert([2025, 2024, 2023, 2022], $loadedYears, 'Quatre années avec données doivent être chargées même si le premier millésime dépasse 400 lignes.');
dvfAssert(404, count($historicalRows), 'Toutes les lignes des quatre millésimes doivent être conservées.');

echo "-- Test avec l’exemple fourni : favorites.json / annonce A91obcjachbl6ue3 (Coursan)\n";
$favoriteExample = [
    'id' => 'A91obcjachbl6ue3',
    'title' => 'Maison de 5 pièces de 117 m² située à Coursan',
    'price' => 287000,
    'priceText' => '287 000 €',
    'surface' => 117,
    'surfaceText' => '',
    'location' => 'Coursan (Aude)',
    'address' => '',
    'rooms' => '5  pièces',
    'coords' => ['lat' => 43.2347407, 'lng' => 3.0585293],
];
$exampleContext = dvfListingContext($favoriteExample);
dvfAssert(117.0, $exampleContext['surface'], 'La surface numérique (117) de l’exemple fourni doit être reprise telle quelle.');
dvfAssert(287000.0, $exampleContext['price'], 'Le prix numérique (287000) de l’exemple fourni doit être repris tel quel.');
dvfAssert(43.2347407, $exampleContext['latitude'], 'Les coordonnées de l’annonce doivent éviter un géocodage par adresse texte.');
dvfAssert('Maison', dvfPropertyType($exampleContext['type']), 'Le titre "Maison de 5 pièces..." doit être classé comme une Maison.');

$exampleCityCode = '11106';
$exampleDepartment = dvfDepartmentCode($exampleCityCode);
dvfAssert('11', $exampleDepartment, 'Coursan (code INSEE 11106) doit être résolue vers le département 11.');
echo "  URL geo-dvf qui sera interrogée pour Coursan/2025: " . dvfCommuneCsvUrl($exampleCityCode, $exampleDepartment, 2025) . "\n";

echo "-- Appel réseau réel vers geo-dvf pour la commune de l’exemple (best-effort)\n";
try {
    $liveRows = dvfRowsForCommune($exampleCityCode);
    echo "  [OK] " . count($liveRows) . " lignes DVF brutes récupérées pour Coursan (11106) depuis files.data.gouv.fr.\n";
    $liveTransactions = dvfNormalizeTransactions($liveRows, ['latitude' => $exampleContext['latitude'], 'longitude' => $exampleContext['longitude']]);
    echo "  [OK] " . count($liveTransactions) . " mutations valides après regroupement/filtrage.\n";
    $liveSelection = dvfSelectComparables($liveTransactions, $exampleContext['type'], $exampleContext['surface'], 10);
    echo "  [OK] " . count($liveSelection['items']) . " comparables sélectionnés (périmètre: " . $liveSelection['perimeter'] . ").\n";
    $GLOBALS['dvfTestCount']++;
} catch (RuntimeException $error) {
    echo "  [IGNORE] Appel réseau vers files.data.gouv.fr indisponible dans cet environnement (" . $error->getMessage() . ").\n";
    echo "  [IGNORE] Ce test doit être relancé sur un environnement avec accès Internet sortant vers data.gouv.fr pour validation complète.\n";
}

echo "=== " . $GLOBALS['dvfTestCount'] . " assertions exécutées ===\n";
echo "dvf_service_test: OK\n";
