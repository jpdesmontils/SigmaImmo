<?php
require_once __DIR__ . '/../app/Database/Migrator.php';

$runner = new Migrator(DatabaseConnection::get(), __DIR__ . '/../migrations');
$ran = $runner->migrate();
$diagnostics = $runner->diagnostics();
$missingTables = missingRequiredTables($diagnostics['tables']);
$warnings = array();

if (!$diagnostics['migrations_dir_exists']) {
    $warnings[] = 'Le dossier des migrations est introuvable: ' . $diagnostics['migrations_dir'];
} elseif (!$diagnostics['migrations_dir_readable']) {
    $warnings[] = 'Le dossier des migrations est illisible: ' . $diagnostics['migrations_dir'];
} elseif (count($diagnostics['migration_files']) === 0) {
    $warnings[] = 'Aucun fichier .sql détecté dans le dossier des migrations.';
}

if (count($missingTables) > 0) {
    $warnings[] = 'Schéma SQLite incomplet, tables manquantes: ' . implode(', ', $missingTables) . '.';
    if (in_array('001_initial_schema.sql', $diagnostics['applied_migrations'], true)) {
        $warnings[] = 'La migration 001_initial_schema.sql est marquée comme appliquée alors que le schéma attendu est absent; vérifier une base incohérente ou supprimer la ligne correspondante de schema_migrations après sauvegarde.';
    }
}

$response = array(
    'ok' => count($missingTables) === 0,
    'database' => DatabaseConnection::path(),
    'migrations' => $ran,
    'warnings' => $warnings,
    'diagnostics' => $diagnostics
);

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($response['ok'] ? 0 : 1);

function missingRequiredTables($tables)
{
    $required = array('schema_migrations', 'users', 'sessions', 'api_tokens', 'properties', 'property_tags', 'analyses', 'analysis_jobs', 'cache_entries', 'legacy_data_files');
    $missing = array();
    foreach ($required as $table) {
        if (!in_array($table, $tables, true)) {
            $missing[] = $table;
        }
    }
    return $missing;
}
