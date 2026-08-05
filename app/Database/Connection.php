<?php
/** Connexion PDO SQLite unique pour le socle applicatif. */
class DatabaseConnection
{
    private static $pdo = null;

    public static function get()
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $path = self::path();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Impossible de créer le répertoire SQLite: ' . $dir);
        }

        self::$pdo = new PDO('sqlite:' . $path);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$pdo->exec('PRAGMA foreign_keys = ON');
        self::$pdo->exec('PRAGMA journal_mode = WAL');
        self::$pdo->exec('PRAGMA busy_timeout = 5000');

        return self::$pdo;
    }

    public static function path()
    {
        $envPath = getenv('SIGMAIMMO_SQLITE_PATH');
        if (is_string($envPath) && trim($envPath) !== '') {
            return $envPath;
        }
        return dirname(dirname(__DIR__)) . '/storage/app.sqlite';
    }
}
