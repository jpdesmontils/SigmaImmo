<?php
require_once __DIR__ . '/Migrator.php';

function sigma_db()
{
    static $ready = false;
    $pdo = DatabaseConnection::get();
    if (!$ready) {
        $migrator = new Migrator($pdo, dirname(dirname(__DIR__)) . '/migrations');
        $migrator->migrate();
        $ready = true;
    }
    return $pdo;
}
