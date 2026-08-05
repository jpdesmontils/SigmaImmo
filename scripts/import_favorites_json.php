<?php
require_once __DIR__ . '/import_json_lib.php';

if (!isset($argv[1]) || trim((string)$argv[1]) === '') {
    fwrite(STDERR, "Usage: php scripts/import_favorites_json.php /chemin/vers/export.json" . PHP_EOL);
    fwrite(STDERR, "Le chemin historique data/favorites.json n'est plus lu implicitement." . PHP_EOL);
    exit(1);
}

printImportResult(importPropertiesJsonFile($argv[1]));
