<?php
require_once __DIR__ . '/../api/analysis_types.php';

function assertSameValue($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nAttendu: " . var_export($expected, true) . "\nReçu: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$root = sys_get_temp_dir() . '/sigma-immo-analysis-' . uniqid('', true) . '/';
foreach (analysisTypes() as $type) mkdir($root . 'analyses/' . $type, 0777, true);

file_put_contents($root . 'analyses/locatif/item.json', json_encode([
    'meta' => ['date_analyse' => '2026-07-20'],
    'note_globale' => ['score' => 71],
    'annonce' => ['revenus' => ['total_annonce_an' => 18800]],
    'exec_summary' => ['rendement_net_min_pct' => 6.1],
]));
file_put_contents($root . 'analyses/patrimonial/item.json', json_encode([
    'meta' => ['date_analyse' => '2026-07-24'],
    'decision' => ['score_global' => 0],
]));
file_put_contents($root . 'analyses/mdb/item.json', json_encode([
    'date_analyse' => '2026-07-22',
    'executive_summary' => ['score' => 105],
]));

$summaries = analysisSummaries($root, 'item');
assertSameValue(71.0, $summaries['locatif']['score'], 'Le score locatif doit être extrait.');
assertSameValue(18800.0, $summaries['locatif']['revenuBrutAnnuel'], 'Le revenu brut annuel locatif doit être exposé sans recalcul.');
assertSameValue(6.1, $summaries['locatif']['rendementNetPct'], 'Le rendement net locatif doit être exposé sans recalcul.');
assertSameValue(0.0, $summaries['patrimonial']['score'], 'Un score nul doit rester affichable.');
assertSameValue(null, $summaries['mdb']['score'], 'Un score hors de /100 doit être ignoré.');

$latest = latestAnalysisSummary($summaries);
assertSameValue('patrimonial', $latest['type'], 'La date métier la plus récente doit déterminer le score de galerie.');
assertSameValue(0.0, $latest['score'], 'La dernière analyse peut légitimement avoir un score nul.');

array_map('unlink', glob($root . 'analyses/*/*.json'));
foreach (array_reverse(glob($root . 'analyses/*', GLOB_ONLYDIR)) as $dir) rmdir($dir);
rmdir($root . 'analyses');
rmdir($root);

echo "analysis_types_test: OK\n";
