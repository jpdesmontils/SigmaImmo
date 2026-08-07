<?php
require_once dirname(__DIR__) . '/Database/Connection.php';

class CacheRepository
{
    private $pdo;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?: DatabaseConnection::get();
    }

    public function get($key)
    {
        $stmt = $this->pdo->prepare('SELECT value_json FROM cache_entries WHERE cache_key = :key AND expires_at >= :now');
        $stmt->execute(array(':key' => (string)$key, ':now' => time()));
        $json = $stmt->fetchColumn();
        if ($json === false) return null;
        $value = json_decode((string)$json, true);
        return is_array($value) ? $value : null;
    }

    public function put($key, $value, $ttl)
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($json === false) throw new RuntimeException('Le cache DVF ne peut pas être encodé.');
        $now = gmdate('c');
        $params = array(':key' => (string)$key, ':value' => $json, ':expires' => time() + (int)$ttl, ':now' => $now);
        $update = $this->pdo->prepare('UPDATE cache_entries SET value_json=:value,expires_at=:expires,updated_at=:now WHERE cache_key=:key');
        $update->execute($params);
        if ($update->rowCount() > 0) return;
        $insert = $this->pdo->prepare('INSERT INTO cache_entries (cache_key,value_json,expires_at,created_at,updated_at) VALUES (:key,:value,:expires,:now,:now)');
        $insert->execute($params);
    }
}
