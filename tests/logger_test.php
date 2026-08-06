<?php
require_once dirname(__DIR__) . '/api/logger.php';

$event = 'test.logger.' . uniqid('', true);
if (!appLog('app', $event, array('source' => 'logger_test'))) {
    fwrite(STDERR, "Le logger n’a pas confirmé l’écriture.\n");
    exit(1);
}

$path = appLogPath('app');
$contents = file_get_contents($path);
if ($contents === false || strpos($contents, $event) === false) {
    fwrite(STDERR, "L’événement de test est absent du fichier de log.\n");
    exit(1);
}

echo "Logger file test passed\n";
