<?php
/** Importe exhaustivement l'ancien data/ puis le supprime après validation. */
require_once __DIR__ . '/../app/Database/bootstrap.php';
require_once __DIR__ . '/../app/Repositories/AnalysisRepository.php';

if (PHP_SAPI !== 'cli') exit(1);
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

$pdo = sigma_db();
$files = legacyDataFiles($rootReal);
$imported = 0; $analyses = 0; $caches = 0;
$pdo->beginTransaction();
try {
    foreach ($files as $file) {
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($rootReal) + 1));
        $mtime = filemtime($file);
        archiveLegacyFile($pdo, $relative, $file, $mtime);
        $imported++;
        if (preg_match('#^cache/(?:dvf/)?([^/]+)\.json$#', $relative, $match)) {
            importLegacyCache($pdo, $match[1], $file, $mtime); $caches++;
        }
        if (preg_match('#^analyses/([a-z0-9_-]+)/([A-Za-z0-9_-]+)\.json$#', $relative, $match) && propertyExists($pdo, $match[2])) {
            $decoded = decodeLegacyJsonFile($file, $relative);
            if (is_array($decoded)) {
                (new AnalysisRepository($pdo))->save($match[2], $match[1], $decoded); $analyses++;
            }
        }
    }
    $count = (int)$pdo->query('SELECT COUNT(*) FROM legacy_data_files')->fetchColumn();
    if ($count < count($files)) throw new RuntimeException('La validation de l’import exhaustif a échoué.');
    $pdo->commit();
} catch (Exception $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $error->getMessage() . "\nAucun fichier n’a été supprimé.\n"); exit(1);
}

try {
    removeLegacyDataTree($rootReal);
} catch (Exception $error) {
    fwrite(STDERR, $error->getMessage() . "\nLes données sont importées, mais le répertoire n’a pas pu être supprimé intégralement.\n"); exit(1);
}
echo 'Migration terminée: ' . $imported . ' fichier(s), ' . $caches . ' cache(s), ' . $analyses . " analyse(s). data/ a été supprimé.\n";

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
    $stmt = $pdo->prepare('INSERT OR REPLACE INTO legacy_data_files (relative_path,contents,modified_at,imported_at) VALUES (:path,:contents,:mtime,:now)');
    $stmt->bindValue(':path', $path, PDO::PARAM_STR); $stmt->bindParam(':contents', $stream, PDO::PARAM_LOB);
    $stmt->bindValue(':mtime', $mtime === false ? null : (int)$mtime, $mtime === false ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':now', gmdate('c'), PDO::PARAM_STR);
    try { $stmt->execute(); } finally { closeLegacyStream($stream); }
}
function importLegacyCache(PDO $pdo, $key, $file, $mtime)
{
    $stream = openLegacyFile($file, 'cache/' . $key . '.json');
    $timestamp = $mtime === false ? time() : (int)$mtime; $now = gmdate('c');
    $stmt = $pdo->prepare('INSERT OR REPLACE INTO cache_entries (cache_key,value_json,expires_at,created_at,updated_at) VALUES (:key,:value,:expires,:now,:now)');
    $stmt->bindValue(':key', $key, PDO::PARAM_STR); $stmt->bindParam(':value', $stream, PDO::PARAM_LOB);
    $stmt->bindValue(':expires', $timestamp + 86400, PDO::PARAM_INT); $stmt->bindValue(':now', $now, PDO::PARAM_STR);
    try { $stmt->execute(); } finally { closeLegacyStream($stream); }
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
