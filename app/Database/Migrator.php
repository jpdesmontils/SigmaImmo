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
        $applied = $this->appliedMigrations();
        $files = glob(rtrim($this->migrationsDir, '/') . '/*.sql');
        sort($files);
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

    private function ensureMigrationsTable()
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (migration TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');
    }

    private function appliedMigrations()
    {
        $rows = $this->pdo->query('SELECT migration FROM schema_migrations')->fetchAll();
        $applied = array();
        foreach ($rows as $row) {
            $applied[$row['migration']] = true;
        }
        return $applied;
    }
}
