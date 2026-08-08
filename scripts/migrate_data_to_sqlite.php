<?php
/** Importe exhaustivement l'ancien data/ puis le supprime après validation. */
require_once __DIR__ . '/../app/Database/bootstrap.php';
require_once __DIR__ . '/../app/Repositories/AnalysisRepository.php';

if (PHP_SAPI !== 'cli') exit(1);
$migrationContext = array('file' => null, 'stage' => 'initialisation');
$migrationEmergencyMemory = str_repeat('x', 65536);
register_shutdown_function('reportMigrationFatalError');
$root = isset($argv[1]) ? rtrim($argv[1], DIRECTORY_SEPARATOR) : dirname(__DIR__) . '/data';
if (!is_dir($root)) {
    echo "Aucun répertoire data à migrer.\n";
    exit(0);
}
$rootReal = realpath($root);
if ($rootReal === false) { fwrite(STDERR, "Répertoire data inaccessible.\n"); exit(1); }
$databaseReal = realpath(DatabaseConnection::path());
if ($databaseReal !== false && strpos($databaseReal, $rootReal . DIRECTORY_SEPARATOR) === 0) {
    fwrite(STDERR, "La base SQLite ne peut pas se trouver dans le répertoire à supprimer.\n"); exit(1);
}

migrationLog('Démarrage de la migration.');
migrationLog('Source: ' . $rootReal);
migrationLog('Base SQLite: ' . DatabaseConnection::path());
migrationLog('Limite mémoire PHP: ' . ini_get('memory_limit') . '.');
$migrationContext['stage'] = 'préparation de la base SQLite';
migrationLog('Préparation de la base SQLite...');
$pdo = sigma_db();
$migrationContext['stage'] = 'inventaire des fichiers';
migrationLog('Inventaire des fichiers à migrer...');
$files = legacyDataFiles($rootReal);
$totalFiles = count($files);
migrationLog($totalFiles . ' fichier(s) trouvé(s).');
$imported = 0; $alreadyImported = 0; $analyses = 0; $caches = 0;
foreach ($files as $index => $file) {
    try {
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($rootReal) + 1));
        $mtime = filemtime($file);
        $size = filesize($file);
        $migrationContext['file'] = $relative;
        $migrationContext['stage'] = 'archivage du fichier';
        migrationLog('[' . ($index + 1) . '/' . $totalFiles . '] ' . $relative . ' (' . formatMigrationBytes($size) . ').');
        if (legacyFileExists($pdo, $relative)) {
            migrationLog('  Fichier déjà présent dans legacy_data_files; suppression de la source.');
            removeMigratedFile($file, $relative);
            $alreadyImported++;
            continue;
        }
        $pdo->beginTransaction();
        migrationLog('  Archivage dans legacy_data_files...');
        archiveLegacyFile($pdo, $relative, $file, $mtime);
        $imported++;
        migrationLog('  Archivage terminé.');
        if (preg_match('#^cache/(?:dvf/)?([^/]+)\.json$#', $relative, $match)) {
            $migrationContext['stage'] = 'import du cache ' . $match[1];
            migrationLog('  Import du cache "' . $match[1] . '"...');
            importLegacyCache($pdo, $match[1], $relative, $mtime); $caches++;
            migrationLog('  Cache importé.');
        }
        if (preg_match('#^analyses/([a-z0-9_-]+)/([A-Za-z0-9_-]+)\.json$#', $relative, $match) && propertyExists($pdo, $match[2])) {
            $migrationContext['stage'] = 'décodage JSON de l’analyse';
            migrationLog('  Décodage JSON de l’analyse...');
            $decoded = decodeLegacyJsonFile($file, $relative);
            if (is_array($decoded)) {
                $migrationContext['stage'] = 'enregistrement de l’analyse';
                migrationLog('  Enregistrement de l’analyse...');
                (new AnalysisRepository($pdo))->save($match[2], $match[1], $decoded); $analyses++;
                migrationLog('  Analyse importée.');
            } else {
                migrationLog('  Analyse ignorée: le contenu JSON n’est pas un tableau.');
            }
        }
        $migrationContext['stage'] = 'validation du fichier archivé';
        validateLegacyFile($pdo, $relative, $size);
        $pdo->commit();
        $migrationContext['stage'] = 'suppression du fichier source';
        removeMigratedFile($file, $relative);
        migrationLog('  Source supprimée après validation.');
        migrationLog('  Fichier terminé; mémoire: ' . formatMigrationBytes(memory_get_usage(true)) . ', pic: ' . formatMigrationBytes(memory_get_peak_usage(true)) . '.');
    } catch (Exception $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        fwrite(STDERR, $error->getMessage() . "\nLe fichier source concerné a été conservé.\n"); exit(1);
    }
}

try {
    $migrationContext['file'] = null;
    $migrationContext['stage'] = 'suppression des répertoires source vides';
    migrationLog('Suppression des répertoires source désormais vides...');
    removeLegacyDataTree($rootReal);
} catch (Exception $error) {
    fwrite(STDERR, $error->getMessage() . "\nLes données sont importées, mais le répertoire n’a pas pu être supprimé intégralement.\n"); exit(1);
}
$migrationContext['stage'] = 'terminée';
echo 'Migration terminée: ' . $imported . ' fichier(s) importé(s), ' . $alreadyImported . ' déjà en base, ' . $caches . ' cache(s), ' . $analyses . " analyse(s). data/ a été supprimé.\n";

function legacyDataFiles($root)
{
    $result = array();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
        if ($item->isLink()) throw new RuntimeException('Lien symbolique refusé: ' . $item->getPathname());
        if ($item->isFile()) $result[] = $item->getPathname();
    }
    sort($result); return $result;
}
function archiveLegacyFile(PDO $pdo, $path, $file, $mtime)
{
    $stream = openLegacyFile($file, $path);
    $stmt = $pdo->prepare('INSERT INTO legacy_data_files (relative_path,contents,modified_at,imported_at) VALUES (:path,X\'\',:mtime,:now)');
    $stmt->bindValue(':path', $path, PDO::PARAM_STR);
    $stmt->bindValue(':mtime', $mtime === false ? null : (int)$mtime, $mtime === false ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':now', gmdate('c'), PDO::PARAM_STR);
    $append = $pdo->prepare('UPDATE legacy_data_files SET contents=CAST(contents || :chunk AS BLOB) WHERE relative_path=:path');
    try {
        $stmt->execute();
        while (!feof($stream)) {
            $chunk = fread($stream, 1048576);
            if ($chunk === false) throw new RuntimeException('Lecture impossible: ' . $path);
            if ($chunk === '') continue;
            $append->bindValue(':chunk', $chunk, PDO::PARAM_LOB);
            $append->bindValue(':path', $path, PDO::PARAM_STR);
            $append->execute();
        }
    } finally { closeLegacyStream($stream); }
}
function importLegacyCache(PDO $pdo, $key, $relative, $mtime)
{
    $timestamp = $mtime === false ? time() : (int)$mtime; $now = gmdate('c');
    $stmt = $pdo->prepare('INSERT OR REPLACE INTO cache_entries (cache_key,value_json,expires_at,created_at,updated_at) SELECT :key,contents,:expires,:now,:now FROM legacy_data_files WHERE relative_path=:path');
    $stmt->bindValue(':key', $key, PDO::PARAM_STR);
    $stmt->bindValue(':expires', $timestamp + 86400, PDO::PARAM_INT); $stmt->bindValue(':now', $now, PDO::PARAM_STR);
    $stmt->bindValue(':path', $relative, PDO::PARAM_STR); $stmt->execute();
}
function legacyFileExists(PDO $pdo, $path)
{
    $stmt = $pdo->prepare('SELECT 1 FROM legacy_data_files WHERE relative_path=:path');
    $stmt->execute(array(':path' => $path)); return (bool)$stmt->fetchColumn();
}
function validateLegacyFile(PDO $pdo, $path, $expectedSize)
{
    $stmt = $pdo->prepare('SELECT length(contents) FROM legacy_data_files WHERE relative_path=:path');
    $stmt->execute(array(':path' => $path)); $actualSize = $stmt->fetchColumn();
    if ($actualSize === false || (int)$actualSize !== (int)$expectedSize) {
        throw new RuntimeException('Validation impossible après archivage: ' . $path);
    }
}
function removeMigratedFile($file, $relative)
{
    if (!unlink($file)) throw new RuntimeException('Suppression impossible: ' . $relative);
}
function openLegacyFile($file, $label)
{
    $stream = fopen($file, 'rb');
    if ($stream === false) throw new RuntimeException('Fichier illisible: ' . $label);
    return $stream;
}
function closeLegacyStream(&$stream)
{
    // PDO SQLite remplace parfois la ressource liée par son contenu après execute().
    if (is_resource($stream)) fclose($stream);
    $stream = null;
}
function decodeLegacyJsonFile($file, $relative)
{
    $contents = file_get_contents($file);
    if ($contents === false) throw new RuntimeException('Fichier illisible: ' . $relative);
    return json_decode($contents, true);
}
function propertyExists(PDO $pdo, $id)
{
    $stmt = $pdo->prepare('SELECT 1 FROM properties WHERE id=:id'); $stmt->execute(array(':id'=>$id)); return (bool)$stmt->fetchColumn();
}
function removeLegacyDataTree($root)
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $ok = $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        if (!$ok) throw new RuntimeException('Suppression impossible: ' . $item->getPathname());
    }
    if (!rmdir($root)) throw new RuntimeException('Suppression impossible: ' . $root);
}
function migrationLog($message)
{
    echo '[' . gmdate('H:i:s') . '] ' . $message . "\n";
    if (function_exists('flush')) flush();
}
function formatMigrationBytes($bytes)
{
    if ($bytes === false) return 'taille inconnue';
    $units = array('o', 'Kio', 'Mio', 'Gio', 'Tio');
    $value = (float)$bytes; $unit = 0;
    while ($value >= 1024 && $unit < count($units) - 1) { $value /= 1024; $unit++; }
    return ($unit === 0 ? (string)(int)$value : number_format($value, 2, ',', ' ')) . ' ' . $units[$unit];
}
function reportMigrationFatalError()
{
    global $migrationContext, $migrationEmergencyMemory;
    $error = error_get_last();
    if (!is_array($error) || !in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) return;
    $migrationEmergencyMemory = null;
    $file = $migrationContext['file'] === null ? '' : ' Fichier: ' . $migrationContext['file'] . '.';
    migrationLog('ARRÊT FATAL pendant l’étape « ' . $migrationContext['stage'] . ' ».' . $file);
    migrationLog('Mémoire au dernier relevé: ' . formatMigrationBytes(memory_get_usage(true)) . '; pic: ' . formatMigrationBytes(memory_get_peak_usage(true)) . '.');
}
