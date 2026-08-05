<?php
require_once __DIR__ . '/../app/Database/Migrator.php';
require_once __DIR__ . '/../app/Repositories/PropertyRepository.php';
require_once __DIR__ . '/../app/Repositories/UserRepository.php';

function importPropertiesJsonFile($importPath)
{
    $runner = new Migrator(DatabaseConnection::get(), __DIR__ . '/../migrations');
    $runner->migrate();

    if (!is_file($importPath)) {
        return array(
            'ok' => true,
            'imported' => 0,
            'message' => 'Aucun fichier JSON à importer.',
            'file' => $importPath
        );
    }

    $decoded = json_decode(file_get_contents($importPath), true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "Fichier JSON invalide: " . $importPath . PHP_EOL);
        exit(1);
    }

    $backupPath = $importPath . '.bak-' . gmdate('YmdHis');
    if (!copy($importPath, $backupPath)) {
        fwrite(STDERR, "Sauvegarde impossible: " . $backupPath . PHP_EOL);
        exit(1);
    }

    $pdo = DatabaseConnection::get();
    $repo = new PropertyRepository($pdo);
    $legacyUser = (new UserRepository($pdo))->ensureLegacyImportUser();
    $count = 0;
    foreach ($decoded as $item) {
        if (!is_array($item)) continue;
        $item['userId'] = (int)$legacyUser['id'];
        $item['visibility'] = 'shared';
        if ($repo->upsert($item, $legacyUser)) $count++;
    }

    return array(
        'ok' => true,
        'imported' => $count,
        'backup' => $backupPath,
        'database' => DatabaseConnection::path()
    );
}

function printImportResult($result)
{
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
}
