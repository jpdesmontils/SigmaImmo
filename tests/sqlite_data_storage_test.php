<?php
require_once __DIR__ . '/../app/Database/Migrator.php';
require_once __DIR__ . '/../app/Repositories/CacheRepository.php';
require_once __DIR__ . '/../app/Repositories/AnalysisRepository.php';

$path = tempnam(sys_get_temp_dir(), 'sigma_storage_');
if ($path === false) exit(1);
unlink($path); putenv('SIGMAIMMO_SQLITE_PATH=' . $path);
$pdo = DatabaseConnection::get();
(new Migrator($pdo, __DIR__ . '/../migrations'))->migrate();
$now = gmdate('c');
$stmt = $pdo->prepare('INSERT INTO properties (id,raw_json,created_at,updated_at) VALUES (:id,\'{}\',:now,:now)');
$stmt->execute(array(':id'=>'property-1', ':now'=>$now));

$cache = new CacheRepository($pdo); $cache->put('rows_06029_2025', array(array('id'=>1)), 60);
assertStorage(array(array('id'=>1)), $cache->get('rows_06029_2025'), 'Le cache DVF doit être relu depuis SQLite.');

$analysis = array('input'=>array('exact_address'=>'Nice'), 'result'=>array('transactions'=>array()));
$analyses = new AnalysisRepository($pdo); $analyses->save('property-1', 'prix', $analysis);
assertStorage($analysis, $analyses->find('property-1', 'prix'), 'L’analyse Prix doit être relue depuis SQLite.');

@unlink($path); @unlink($path . '-wal'); @unlink($path . '-shm');
echo "sqlite_data_storage_test ok\n";

function assertStorage($expected, $actual, $message)
{
    if ($expected !== $actual) { fwrite(STDERR, $message . "\n"); exit(1); }
}
