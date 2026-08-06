<?php
require_once dirname(__DIR__) . '/Database/Connection.php';

class ApiTokenRepository
{
    private $pdo;

    public function __construct(PDO $pdo = null) { $this->pdo = $pdo ?: DatabaseConnection::get(); }

    public function create($userId, $tokenHash, $label, $expiresAt = null)
    {
        $stmt = $this->pdo->prepare('INSERT INTO api_tokens (user_id, token_hash, label, expires_at, created_at) VALUES (:user_id, :token_hash, :label, :expires_at, :created_at)');
        $stmt->execute(array(':user_id' => (int)$userId, ':token_hash' => $tokenHash, ':label' => trim((string)$label), ':expires_at' => $expiresAt, ':created_at' => gmdate('c')));
        return (int)$this->pdo->lastInsertId();
    }

    public function findValidByHash($hash)
    {
        $stmt = $this->pdo->prepare('SELECT t.*, u.email, u.name, u.plan FROM api_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = :hash AND (t.expires_at IS NULL OR t.expires_at > :now) LIMIT 1');
        $stmt->execute(array(':hash' => $hash, ':now' => gmdate('c')));
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function allForUser($userId)
    {
        $stmt = $this->pdo->prepare('SELECT id, label, last_used_at, expires_at, created_at FROM api_tokens WHERE user_id = :user_id ORDER BY id DESC');
        $stmt->execute(array(':user_id' => (int)$userId));
        return $stmt->fetchAll();
    }

    public function touch($id)
    {
        $stmt = $this->pdo->prepare('UPDATE api_tokens SET last_used_at = :now WHERE id = :id');
        $stmt->execute(array(':id' => (int)$id, ':now' => gmdate('c')));
    }

    public function deleteForUser($id, $userId)
    {
        $stmt = $this->pdo->prepare('DELETE FROM api_tokens WHERE id = :id AND user_id = :user_id');
        $stmt->execute(array(':id' => (int)$id, ':user_id' => (int)$userId));
        return $stmt->rowCount() > 0;
    }
}
