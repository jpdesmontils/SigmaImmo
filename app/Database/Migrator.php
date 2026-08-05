<?php
require_once __DIR__ . '/Connection.php';

class Migrator
{
    private $pdo;
    private $migrationsDir;

    public function __construct(PDO $pdo, $migrationsDir)
    {
        $this->pdo = $pdo;
        $this->migrationsDir = $migrationsDir;
    }

    public function migrate()
    {
        $this->ensureMigrationsTable();
        $applied = $this->appliedMigrationsMap();
        $files = $this->migrationFiles();
        $ran = array();

        foreach ($files as $file) {
            $name = basename($file);
            if (isset($applied[$name])) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('Migration illisible: ' . $file);
            }
            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec($sql);
                $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (migration, applied_at) VALUES (:migration, :applied_at)');
                $stmt->execute(array(':migration' => $name, ':applied_at' => gmdate('c')));
                $this->pdo->commit();
                $ran[] = $name;
            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }

        return $ran;
    }

    public function diagnostics()
    {
        $path = DatabaseConnection::path();
        $dir = dirname($path);
        return array(
            'database_path' => $path,
            'database_exists' => is_file($path),
            'database_size_bytes' => is_file($path) ? filesize($path) : null,
            'database_dir' => $dir,
            'database_dir_exists' => is_dir($dir),
            'database_dir_writable' => is_dir($dir) ? is_writable($dir) : false,
            'migrations_dir' => $this->migrationsDir,
            'migrations_dir_realpath' => realpath($this->migrationsDir) ?: null,
            'migrations_dir_exists' => is_dir($this->migrationsDir),
            'migrations_dir_readable' => is_dir($this->migrationsDir) ? is_readable($this->migrationsDir) : false,
            'migration_files' => array_map('basename', $this->migrationFiles()),
            'applied_migrations' => $this->appliedMigrationsList(),
            'pending_migrations' => $this->pendingMigrations(),
            'tables' => $this->tables()
        );
    }

    private function ensureMigrationsTable()
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (migration TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');
    }

    private function appliedMigrationsMap()
    {
        $rows = $this->pdo->query('SELECT migration FROM schema_migrations')->fetchAll();
        $applied = array();
        foreach ($rows as $row) {
            $applied[$row['migration']] = true;
        }
        return $applied;
    }

    private function appliedMigrationsList()
    {
        $rows = $this->pdo->query('SELECT migration FROM schema_migrations ORDER BY migration')->fetchAll();
        $applied = array();
        foreach ($rows as $row) {
            $applied[] = $row['migration'];
        }
        return $applied;
    }

    private function pendingMigrations()
    {
        $applied = $this->appliedMigrationsMap();
        $pending = array();
        foreach ($this->migrationFiles() as $file) {
            $name = basename($file);
            if (!isset($applied[$name])) {
                $pending[] = $name;
            }
        }
        return $pending;
    }

    private function migrationFiles()
    {
        if (!is_dir($this->migrationsDir) || !is_readable($this->migrationsDir)) {
            return array();
        }
        $files = glob(rtrim($this->migrationsDir, '/') . '/*.sql');
        if (!is_array($files)) {
            return array();
        }
        sort($files);
        return $files;
    }

    private function tables()
    {
        $rows = $this->pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll();
        $tables = array();
        foreach ($rows as $row) {
            $tables[] = $row['name'];
        }
        return $tables;
    }
}
