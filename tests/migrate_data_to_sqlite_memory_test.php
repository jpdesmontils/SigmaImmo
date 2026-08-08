<?php
$temporary = sys_get_temp_dir() . '/sigma_migration_' . uniqid('', true);
$data = $temporary . '/data';
$cacheDirectory = $data . '/cache/dvf';
$database = $temporary . '/app.sqlite';
if (!mkdir($cacheDirectory, 0755, true)) exit(1);

$cacheFile = $cacheDirectory . '/large.json';
$stream = fopen($cacheFile, 'wb');
if ($stream === false) exit(1);
fwrite($stream, '[');
$block = str_repeat('0,', 1024 * 1024);
for ($index = 0; $index < 8; $index++) fwrite($stream, $block);
fwrite($stream, '0]');
fclose($stream);
$expectedSize = filesize($cacheFile);

$script = realpath(__DIR__ . '/../scripts/migrate_data_to_sqlite.php');
$command = 'SIGMAIMMO_SQLITE_PATH=' . escapeshellarg($database)
    . ' ' . escapeshellarg(PHP_BINARY) . ' -d memory_limit=32M '
    . escapeshellarg($script) . ' ' . escapeshellarg($data);
passthru($command, $status);
if ($status !== 0) failMigrationTest('La migration doit réussir avec une limite mémoire de 32 Mo.', $temporary);
if (is_dir($data)) failMigrationTest('Le répertoire source doit être supprimé après validation.', $temporary);

$pdo = new PDO('sqlite:' . $database);
$archivedSize = (int)$pdo->query('SELECT length(contents) FROM legacy_data_files')->fetchColumn();
$cacheSize = (int)$pdo->query('SELECT length(value_json) FROM cache_entries')->fetchColumn();
if ($archivedSize !== $expectedSize || $cacheSize !== $expectedSize) {
    failMigrationTest('Le cache doit être importé intégralement dans les deux tables.', $temporary);
}

if (!mkdir($cacheDirectory, 0755, true)) failMigrationTest('Le cache déjà importé doit pouvoir être recréé.', $temporary);
file_put_contents($cacheFile, '[1]');
$output = array();
exec($command . ' 2>&1', $output, $status);
if ($status !== 0) failMigrationTest('La reprise de migration doit réussir.', $temporary);
if (is_dir($data)) failMigrationTest('La source déjà présente en base doit être supprimée.', $temporary);
if (strpos(implode("\n", $output), 'Fichier déjà présent dans legacy_data_files; suppression de la source.') === false) {
    failMigrationTest('La reprise doit signaler le fichier déjà présent en base.', $temporary);
}
$archivedSizeAfterResume = (int)$pdo->query('SELECT length(contents) FROM legacy_data_files')->fetchColumn();
if ($archivedSizeAfterResume !== $expectedSize) {
    failMigrationTest('La reprise ne doit pas remplacer un fichier déjà archivé.', $temporary);
}

$pdo = null;
removeMigrationTestTree($temporary);
echo "migrate_data_to_sqlite_memory_test ok\n";

function failMigrationTest($message, $temporary)
{
    removeMigrationTestTree($temporary);
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function removeMigrationTestTree($root)
{
    if (!is_dir($root)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) rmdir($item->getPathname());
        else unlink($item->getPathname());
    }
    rmdir($root);
}
