<?php
require_once __DIR__ . '/../api/analysis_types.php';

$promptsDir = __DIR__ . '/../resources/prompts/';
$promptNames = array('ana_trajet_rp', 'ana_patrimonial');
foreach (analysisTypes() as $type) $promptNames[] = 'ana_' . $type;

foreach (array_unique($promptNames) as $promptName) {
    $path = $promptsDir . $promptName . '.txt';
    if (!is_file($path) || trim((string)file_get_contents($path)) === '') {
        fwrite(STDERR, 'Prompt applicatif introuvable ou vide : ' . $promptName . ".\n");
        exit(1);
    }
}

$worker = file_get_contents(__DIR__ . '/../api/analyze_worker.php');
if (strpos($worker, "define('PROMPTS_DIR', __DIR__ . '/../resources/prompts/');") === false
    || strpos($worker, "file_get_contents(PROMPTS_DIR . \$promptName . '.txt')") === false) {
    fwrite(STDERR, "Le worker ne charge pas les prompts depuis resources/prompts.\n");
    exit(1);
}

echo "analysis_prompts_test: OK\n";
