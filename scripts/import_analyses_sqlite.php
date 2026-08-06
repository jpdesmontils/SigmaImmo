<?php
require_once __DIR__ . '/../app/Database/bootstrap.php';

$root = isset($argv[1]) ? rtrim($argv[1], '/') : dirname(__DIR__) . '/data/analyses';
if (!is_dir($root)) { fwrite(STDERR, "Répertoire d'analyses introuvable: " . $root . "\n"); exit(1); }
$pdo = sigma_db(); $imported = 0; $skipped = 0; $now = gmdate('c');
$pdo->beginTransaction();
try {
    $directories = glob($root . '/*', GLOB_ONLYDIR);
    foreach ($directories ?: array() as $directory) {
        $type = basename($directory); if ($type === 'jobs' || !preg_match('/^[a-z0-9_-]{1,60}$/', $type)) continue;
        $files = glob($directory . '/*.json');
        foreach ($files ?: array() as $file) {
            $propertyId = basename($file, '.json');
            $property = $pdo->prepare('SELECT 1 FROM properties WHERE id = :id'); $property->execute(array(':id' => $propertyId));
            if (!$property->fetchColumn()) { fwrite(STDERR, "Ignorée (bien absent): " . $file . "\n"); $skipped++; continue; }
            $value = json_decode(file_get_contents($file), true); if (!is_array($value)) { fwrite(STDERR, "Ignorée (JSON invalide): " . $file . "\n"); $skipped++; continue; }
            $exists = $pdo->prepare('SELECT 1 FROM analyses WHERE property_id = :property_id AND type = :type'); $exists->execute(array(':property_id' => $propertyId, ':type' => $type));
            if ($exists->fetchColumn()) { $skipped++; continue; }
            $score = isset($value['score']) && is_numeric($value['score']) ? (float)$value['score'] : null;
            $stmt = $pdo->prepare('INSERT INTO analyses (property_id,type,status,summary_json,result_json,score,created_at,updated_at) VALUES (:property_id,:type,\'completed\',:summary,:result,:score,:now,:now)');
            $stmt->execute(array(':property_id' => $propertyId, ':type' => $type, ':summary' => json_encode(array('score' => $score), JSON_UNESCAPED_UNICODE), ':result' => json_encode($value, JSON_UNESCAPED_UNICODE), ':score' => $score, ':now' => $now)); $imported++;
        }
    }
    $jobs = glob($root . '/jobs/*.json');
    foreach ($jobs ?: array() as $file) {
        $job = json_decode(file_get_contents($file), true); if (!is_array($job) || empty($job['id']) || empty($job['type'])) { $skipped++; continue; }
        $property = $pdo->prepare('SELECT 1 FROM properties WHERE id = :id'); $property->execute(array(':id' => $job['id'])); if (!$property->fetchColumn()) { $skipped++; continue; }
        $stmt = $pdo->prepare('INSERT INTO analysis_jobs (property_id,type,status,queued_at,started_at,lease_expires_at,finished_at,error,created_at,updated_at) SELECT :property_id,:type,:status,:queued_at,:started_at,:lease_expires_at,:finished_at,:error,:now,:now WHERE NOT EXISTS (SELECT 1 FROM analysis_jobs WHERE property_id=:property_id AND type=:type AND status=:status)');
        $stmt->execute(array(':property_id' => $job['id'], ':type' => $job['type'], ':status' => isset($job['status']) ? $job['status'] : 'failed', ':queued_at' => isset($job['queued_at']) ? $job['queued_at'] : $now, ':started_at' => isset($job['started_at']) ? $job['started_at'] : null, ':lease_expires_at' => isset($job['lease_expires_at']) ? $job['lease_expires_at'] : null, ':finished_at' => isset($job['finished_at']) ? $job['finished_at'] : null, ':error' => isset($job['error']) ? $job['error'] : null, ':now' => $now)); $imported += $stmt->rowCount();
    }
    $pdo->commit();
} catch (Exception $e) { $pdo->rollBack(); fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
echo 'Import SQLite terminé: ' . $imported . ' importée(s), ' . $skipped . " ignorée(s). Les fichiers source peuvent maintenant être archivés.\n";
