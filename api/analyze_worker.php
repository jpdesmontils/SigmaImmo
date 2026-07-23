<?php
/** Worker CLI : appelle OpenAI puis enregistre strictement le JSON produit. */
require_once __DIR__ . '/logger.php';
if (PHP_SAPI !== 'cli') exit(1);
define('DATA_DIR', __DIR__ . '/../data/');
define('FAVORITES_FILE', DATA_DIR . 'favorites.json');
define('JOBS_DIR', DATA_DIR . 'analyses/jobs/');
$id = $argv[1] ?? ''; $type = $argv[2] ?? '';
if (!preg_match('/^[A-Za-z0-9_-]{1,180}$/', $id) || !in_array($type, ['locatif', 'mdb'], true)) exit(1);
$jobPath = JOBS_DIR . $id . '.json';
aiLog('analysis.worker_started', ['id' => $id, 'type' => $type]);
try {
    loadEnv(__DIR__ . '/.env');
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) throw new RuntimeException('OPENAI_API_KEY est absente de api/.env.');
    $listing = findFavorite($id);
    if (!$listing || ($listing['selection'] ?? '') !== 'invest') throw new RuntimeException('Annonce Invest introuvable.');
    $promptFile = DATA_DIR . 'prompts/ana_' . $type . '.txt';
    $template = @file_get_contents($promptFile);
    if (!$template) throw new RuntimeException('Prompt d’analyse introuvable ou vide.');
    $prompt = str_replace('{{annonce_complete}}', json_encode($listing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), $template);
    aiLog('analysis.openai_request_started', ['id' => $id, 'type' => $type]);
    $result = requestOpenAi($apiKey, $prompt);
    aiLog('analysis.openai_request_succeeded', ['id' => $id, 'type' => $type]);
    $analysis = json_decode($result, true);
    if (!is_array($analysis)) throw new RuntimeException('OpenAI n’a pas renvoyé de JSON valide.');
    $dir = DATA_DIR . 'analyses/' . $type . '/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Répertoire d’analyse inaccessible.');
    if (file_put_contents($dir . $id . '.json', json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) throw new RuntimeException('Écriture du fichier d’analyse impossible.');
    aiLog('analysis.result_written', ['id' => $id, 'type' => $type, 'path' => $dir . $id . '.json']);
    finish($jobPath, ['id' => $id, 'type' => $type, 'status' => 'completed', 'finished_at' => gmdate('c'), 'error' => null]);
    aiLog('analysis.completed', ['id' => $id, 'type' => $type]);
} catch (Throwable $error) {
    aiLog('analysis.failed', ['id' => $id, 'type' => $type, 'error' => $error->getMessage()]);
    finish($jobPath, ['id' => $id, 'type' => $type, 'status' => 'failed', 'finished_at' => gmdate('c'), 'error' => $error->getMessage()]);
}
function loadEnv($path) { if (!is_file($path)) return; foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) { $line = trim($line); if ($line === '' || $line[0] === '#') continue; $line = preg_replace('/^export\s+/', '', $line); if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $m)) continue; $value = trim($m[2]); if (strlen($value) > 1 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) $value = substr($value, 1, -1); putenv($m[1] . '=' . $value); $_ENV[$m[1]] = $value; } }
function findFavorite($id) { $items = json_decode(@file_get_contents(FAVORITES_FILE), true) ?: []; foreach ($items as $item) if (isset($item['id']) && (string)$item['id'] === $id) return $item; return null; }
function requestOpenAi($apiKey, $prompt) { if (!function_exists('curl_init')) throw new RuntimeException('Extension PHP cURL indisponible.'); $payload = ['model' => 'gpt-5.4', 'input' => [['role' => 'user', 'content' => [['type' => 'input_text', 'text' => $prompt]]]], 'text' => ['format' => ['type' => 'json_object']]]; $curl = curl_init('https://api.openai.com/v1/responses'); curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 300, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE)]); $body = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE); $error = curl_error($curl); curl_close($curl); if ($body === false || $status < 200 || $status >= 300) throw new RuntimeException('Erreur OpenAI (' . $status . '): ' . ($error ?: 'requête refusée')); $decoded = json_decode($body, true); foreach (($decoded['output'] ?? []) as $output) foreach (($output['content'] ?? []) as $content) if (($content['type'] ?? '') === 'output_text') return $content['text']; throw new RuntimeException('Réponse OpenAI sans contenu texte.'); }
function finish($path, $data) { $previous = json_decode(@file_get_contents($path), true) ?: []; file_put_contents($path, json_encode(array_merge($previous, $data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX); }
