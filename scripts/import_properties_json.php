<?php
require_once __DIR__ . '/import_json_lib.php';

if (!isset($argv[1]) || trim((string)$argv[1]) === '') {
    fwrite(STDERR, "Usage: php scripts/import_properties_json.php /chemin/vers/export.json" . PHP_EOL);
    exit(1);
}
$importPath = $argv[1];
printImportResult(importPropertiesJsonFile($importPath));
