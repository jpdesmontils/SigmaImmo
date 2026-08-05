<?php
require_once __DIR__ . '/../app/Database/Migrator.php';
require_once __DIR__ . '/../app/Repositories/PropertyRepository.php';

$favoritePath = isset($argv[1]) ? $argv[1] : (__DIR__ . '/../data/favorites.json');
$backupPath = $favoritePath . '.bak-' . gmdate('YmdHis');
$runner = new Migrator(DatabaseConnection::get(), __DIR__ . '/../migrations');
$runner->migrate();

if (!is_file($favoritePath)) {
    echo json_encode(array('ok' => true, 'imported' => 0, 'message' => 'Aucun fichier JSON à importer.', 'file' => $favoritePath), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$decoded = json_decode(file_get_contents($favoritePath), true);
if (!is_array($decoded)) {
    fwrite(STDERR, "Fichier JSON invalide: " . $favoritePath . PHP_EOL);
    exit(1);
}

if (!copy($favoritePath, $backupPath)) {
    fwrite(STDERR, "Sauvegarde impossible: " . $backupPath . PHP_EOL);
    exit(1);
}

$repo = new PropertyRepository();
$count = 0;
foreach ($decoded as $item) {
    if (!is_array($item)) continue;
    if ($repo->upsert($item)) $count++;
}

echo json_encode(array('ok' => true, 'imported' => $count, 'backup' => $backupPath, 'database' => DatabaseConnection::path()), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
