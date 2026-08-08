<?php
require_once __DIR__ . '/../app/Database/Migrator.php';

$database = tempnam(sys_get_temp_dir(), 'sigma_attributes_');
$migrations = sys_get_temp_dir() . '/sigma_migrations_' . uniqid('', true);
if ($database === false || !mkdir($migrations)) exit(1);

foreach (glob(__DIR__ . '/../migrations/*.sql') as $file) {
    if (basename($file) === '009_property_user_attributes.sql') continue;
    copy($file, $migrations . '/' . basename($file));
}

$pdo = new PDO('sqlite:' . $database);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
(new Migrator($pdo, $migrations))->migrate();

$now = gmdate('c');
$pdo->prepare("INSERT INTO users (email,plan,created_at,updated_at) VALUES ('migration@example.test','free',:now,:now)")->execute(array(':now'=>$now));
$userId = (int)$pdo->lastInsertId();
$stmt = $pdo->prepare("INSERT INTO properties (id,user_id,title,selection,notes,visit_at,agent_name,raw_json,created_at,updated_at) VALUES ('legacy-property',:user_id,'Legacy','visite','Privé','2026-08-08T10:00','Agent','{\"selection\":\"visite\",\"notes\":\"Privé\",\"title\":\"Legacy\"}',:now,:now)");
$stmt->execute(array(':user_id'=>$userId, ':now'=>$now));

copy(__DIR__ . '/../migrations/009_property_user_attributes.sql', $migrations . '/009_property_user_attributes.sql');
(new Migrator($pdo, $migrations))->migrate();

$attributes = $pdo->query('SELECT * FROM property_user_attributes WHERE property_id="legacy-property"')->fetch();
assertMigration($attributes['user_id'] === $userId && $attributes['status'] === 'visite' && $attributes['notes'] === 'Privé', 'les attributs historiques doivent être attribués au propriétaire');
$property = $pdo->query('SELECT * FROM properties WHERE id="legacy-property"')->fetch();
assertMigration(!array_key_exists('selection', $property) && !array_key_exists('notes', $property), 'les colonnes personnelles doivent être retirées des annonces');
$raw = json_decode($property['raw_json'], true);
assertMigration(!isset($raw['selection']) && !isset($raw['notes']) && $raw['title'] === 'Legacy', 'les attributs personnels doivent être retirés du JSON brut sans perdre les données partagées');
assertMigration($pdo->query('PRAGMA foreign_key_check')->fetch() === false, 'la migration doit préserver les clés étrangères');

foreach (glob($migrations . '/*.sql') as $file) unlink($file);
rmdir($migrations); unlink($database);
echo "property_user_attributes_migration_test ok\n";

function assertMigration($condition, $message) { if (!$condition) { fwrite(STDERR, 'Assertion failed: ' . $message . "\n"); exit(1); } }
