<?php
/** Worker CLI : appelle OpenAI puis enregistre strictement le JSON produit. */
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/analysis_types.php';
require_once __DIR__ . '/dvf_service.php';
if (PHP_SAPI !== 'cli') exit(1);
define('DATA_DIR', __DIR__ . '/../data/');
define('FAVORITES_FILE', DATA_DIR . 'favorites.json');
define('JOBS_DIR', DATA_DIR . 'analyses/jobs/');
$id = $argv[1] ?? ''; $type = $argv[2] ?? '';
if (!preg_match('/^[A-Za-z0-9_-]{1,180}$/', $id) || !validAnalysisType($type)) exit(1);
$jobPath = JOBS_DIR . $id . '.json';
$job = json_decode(@file_get_contents($jobPath), true) ?: [];
if (($job['status'] ?? '') !== 'queued' || ($job['type'] ?? '') !== $type) { aiLog('analysis.worker_skipped', ['id' => $id, 'type' => $type, 'status' => $job['status'] ?? null]); exit(1); }
aiLog('analysis.worker_started', ['id' => $id, 'type' => $type]);
try {
    loadEnv(__DIR__ . '/.env');
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) throw new RuntimeException('OPENAI_API_KEY est absente de api/.env.');
    $model = getenv('OPENAI_MODEL') ?: 'gpt-5.3-chat-latest';
    $listing = findFavorite($id);
    if (!$listing) throw new RuntimeException('Annonce favorite introuvable.');
    markRunning($jobPath, $id, $type);
    $inputVariables = promptInputVariables($listing);
    $listingJson = json_encode($listing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $baseReplacements = ['{{annonce_complete}}' => $listingJson, '{{dpe}}' => $inputVariables['dpe'], '{{ges}}' => $inputVariables['ges']];
    if ($type === 'patrimonial') {
        $priceAnalysis = dvfReadAnalysis($id, DATA_DIR);
        if (!$priceAnalysis || !dvfAnalysisMatchesListing($priceAnalysis, $listing)) {
            aiLog('analysis.price_data_started', ['id' => $id, 'type' => $type]);
            $priceAnalysis = dvfCreateAnalysis($id, $listing, DATA_DIR, ['id' => $id, 'source' => 'patrimonial_worker']);
            aiLog('analysis.price_data_succeeded', ['id' => $id, 'type' => $type, 'captured_at' => $priceAnalysis['captured_at']]);
        }
        $travel = runAnalysisStage($apiKey, $model, 'ana_trajet_rp', $baseReplacements + ['{{ville_residence_principale}}' => $inputVariables['ville_residence_principale']], $id, $type);
        applyTravelScore($travel);
        $analysis = runAnalysisStage($apiKey, $model, 'ana_patrimonial', [
            '{{annonce_complete}}' => $listingJson,
            '{{dpe}}' => $inputVariables['dpe'],
            '{{ges}}' => $inputVariables['ges'],
            '{{ville_residence_principale}}' => $inputVariables['ville_residence_principale'],
            '{{analyse_trajet}}' => json_encode($travel, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            '{{analyse_prix}}' => json_encode($priceAnalysis['result'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ], $id, $type);
        $analysis['accessibilite_depuis_rp'] = $travel;
        $analysis['accessibilite_depuis_paris'] = $travel;
        $analysis['notation']['axes']['accessibilite']['score'] = $travel['evaluation']['score'] ?? null;
        $analysis['sources'] = array_merge($analysis['sources'] ?? [], $travel['sources'] ?? []);
        applyPatrimonialScore($analysis);
    } else {
        $analysis = runAnalysisStage($apiKey, $model, 'ana_' . $type, $baseReplacements, $id, $type);
    }
    $dir = DATA_DIR . 'analyses/' . $type . '/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Répertoire d’analyse inaccessible.');
    $resultPath = $dir . $id . '.json';
    if (file_put_contents($resultPath, json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) throw new RuntimeException('Écriture du fichier d’analyse impossible.');
    // Une suppression peut avoir lieu pendant l’appel OpenAI : ne jamais recréer
    // une analyse orpheline une fois que l’annonce a disparu des favoris.
    if (discardFilesForDeletedListing($id, $jobPath, $resultPath)) {
        aiLog('analysis.result_discarded_deleted_listing', ['id' => $id, 'type' => $type]);
        exit(0);
    }
    aiLog('analysis.result_written', ['id' => $id, 'type' => $type, 'path' => $resultPath]);
    finish($jobPath, ['id' => $id, 'type' => $type, 'status' => 'completed', 'finished_at' => gmdate('c'), 'error' => null]);
    if (discardFilesForDeletedListing($id, $jobPath, $resultPath)) exit(0);
    aiLog('analysis.completed', ['id' => $id, 'type' => $type]);
} catch (Throwable $error) {
    aiLog('analysis.failed', ['id' => $id, 'type' => $type, 'error' => $error->getMessage()]);
    if (findFavorite($id)) {
        finish($jobPath, ['id' => $id, 'type' => $type, 'status' => 'failed', 'finished_at' => gmdate('c'), 'error' => $error->getMessage()]);
    }
    discardFilesForDeletedListing($id, $jobPath);
}
function loadEnv($path) { if (!is_file($path)) return; foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) { $line = trim($line); if ($line === '' || $line[0] === '#') continue; $line = preg_replace('/^export\s+/', '', $line); if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $m)) continue; $value = trim($m[2]); if (strlen($value) > 1 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) $value = substr($value, 1, -1); putenv($m[1] . '=' . $value); $_ENV[$m[1]] = $value; } }
function findFavorite($id) { $items = json_decode(@file_get_contents(FAVORITES_FILE), true) ?: []; foreach ($items as $item) if (isset($item['id']) && (string)$item['id'] === $id) return $item; return null; }
function discardFilesForDeletedListing($id, $jobPath, $resultPath = null) { if (findFavorite($id)) return false; if ($resultPath) @unlink($resultPath); @unlink($jobPath); return true; }
function promptInputVariables($listing) {
    $dpe = strtoupper(trim((string)(isset($listing['dpe']) ? $listing['dpe'] : '')));
    $ges = strtoupper(trim((string)(isset($listing['ges']) ? $listing['ges'] : '')));
    $city = trim((string)(isset($listing['primaryResidenceCity']) ? $listing['primaryResidenceCity'] : ''));
    if ($city === '') $city = 'Paris';
    return ['dpe' => $dpe, 'ges' => $ges, 'ville_residence_principale' => $city];
}
function runAnalysisStage($apiKey, $model, $promptName, $replacements, $id, $type) {
    $template = @file_get_contents(DATA_DIR . 'prompts/' . $promptName . '.txt');
    if (!$template) throw new RuntimeException('Prompt d’analyse introuvable ou vide : ' . $promptName . '.');
    $prompt = str_replace(array_keys($replacements), array_values($replacements), $template);
    aiLog('analysis.openai_request_started', ['id' => $id, 'type' => $type, 'stage' => $promptName]);
    $result = requestOpenAi($apiKey, $model, $prompt);
    aiLog('analysis.openai_request_succeeded', ['id' => $id, 'type' => $type, 'stage' => $promptName]);
    $decoded = json_decode($result, true);
    if (!is_array($decoded)) throw new RuntimeException('OpenAI n’a pas renvoyé de JSON valide pour l’étape ' . $promptName . '.');
    return $decoded;
}
function applyTravelScore(&$travel) {
    $maximums = ['duree' => 35, 'frequence' => 25, 'simplicite' => 15, 'week_end' => 15, 'dernier_kilometre' => 10];
    $criteria = $travel['evaluation']['criteres'] ?? null;
    if (!is_array($criteria)) throw new RuntimeException('Le détail de la note d’accessibilité est absent.');
    $total = 0.0;
    foreach ($maximums as $code => $maximum) {
        if (!is_numeric($criteria[$code]['points'] ?? null)) throw new RuntimeException('Note d’accessibilité absente pour le critère ' . $code . '.');
        $points = max(0, min($maximum, (float)$criteria[$code]['points']));
        $travel['evaluation']['criteres'][$code]['points'] = round($points, 1);
        $travel['evaluation']['criteres'][$code]['maximum'] = $maximum;
        $total += $points;
    }
    $travel['evaluation']['score'] = round($total);
    $travel['evaluation']['verdict'] = $total >= 80 ? 'excellent' : ($total >= 65 ? 'bon' : ($total >= 45 ? 'moyen' : 'faible'));
}
function applyPatrimonialScore(&$analysis) {
    $expectedWeights = ['emplacement_valeur' => 25, 'qualite_bati' => 15, 'liquidite_rarete' => 15, 'risques' => 15, 'financement' => 15, 'optimisations' => 10, 'accessibilite' => 5];
    $axes = $analysis['notation']['axes'] ?? null;
    if (!is_array($axes)) throw new RuntimeException('La grille de notation patrimoniale est absente.');
    $total = 0.0;
    foreach ($expectedWeights as $code => $weight) {
        if (!isset($axes[$code]) || !is_array($axes[$code]) || !is_numeric($axes[$code]['score'] ?? null)) throw new RuntimeException('Score patrimonial absent pour l’axe ' . $code . '.');
        $score = max(0, min(100, (float)$axes[$code]['score']));
        $contribution = $score * $weight / 100;
        $analysis['notation']['axes'][$code]['score'] = round($score, 1);
        $analysis['notation']['axes'][$code]['poids_pct'] = $weight;
        $analysis['notation']['axes'][$code]['contribution_points'] = round($contribution, 1);
        $total += $contribution;
    }
    $analysis['notation']['score_global'] = round($total);
    $analysis['decision']['score_global'] = $analysis['notation']['score_global'];
    $analysis['qualite_patrimoniale']['score'] = round(($axes['emplacement_valeur']['score'] * 25 + $axes['qualite_bati']['score'] * 15 + $axes['liquidite_rarete']['score'] * 15) / 55);
}
function requestOpenAi($apiKey, $model, $prompt) {
    if (!function_exists('curl_init')) throw new RuntimeException('Extension PHP cURL indisponible.');
    $payload = ['model' => $model, 'input' => [['role' => 'user', 'content' => [['type' => 'input_text', 'text' => $prompt]]]], 'text' => ['format' => ['type' => 'json_object']]];
    $curl = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 300, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($body === false || $status < 200 || $status >= 300) {
        $details = openAiErrorDetails($body, $error);
        throw new RuntimeException('Erreur OpenAI (' . $status . ') avec le modèle ' . $model . ' : ' . $details);
    }
    $decoded = json_decode($body, true);
    foreach (($decoded['output'] ?? []) as $output) foreach (($output['content'] ?? []) as $content) if (($content['type'] ?? '') === 'output_text') return $content['text'];
    throw new RuntimeException('Réponse OpenAI sans contenu texte.');
}
function openAiErrorDetails($body, $curlError) {
    if ($curlError) return $curlError;
    $decoded = json_decode((string)$body, true);
    $error = $decoded['error'] ?? null;
    if (is_array($error)) {
        $parts = array_filter([$error['message'] ?? null, isset($error['type']) ? 'type=' . $error['type'] : null, isset($error['code']) ? 'code=' . $error['code'] : null]);
        if ($parts) return implode(' | ', $parts);
    }
    return 'réponse refusée sans détail exploitable';
}
function markRunning($path, $id, $type) { finish($path, ['id' => $id, 'type' => $type, 'status' => 'running', 'started_at' => gmdate('c'), 'lease_expires_at' => gmdate('c', time() + 620), 'finished_at' => null, 'error' => null]); }
function finish($path, $data) { $previous = json_decode(@file_get_contents($path), true) ?: []; file_put_contents($path, json_encode(array_merge($previous, $data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX); }
