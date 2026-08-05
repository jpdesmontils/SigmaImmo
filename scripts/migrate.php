<?php
require_once __DIR__ . '/../app/Database/Migrator.php';
$runner = new Migrator(DatabaseConnection::get(), __DIR__ . '/../migrations');
$ran = $runner->migrate();
echo json_encode(array('ok' => true, 'database' => DatabaseConnection::path(), 'migrations' => $ran), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
