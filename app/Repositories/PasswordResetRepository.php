<?php
require_once dirname(__DIR__) . '/Database/Connection.php';

class PasswordResetRepository
{
    private $pdo;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?: DatabaseConnection::get();
    }

    public function create($userId, $tokenHash, $expiresAt)
    {
        $stmt = $this->pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (:user_id, :token_hash, :expires_at, :created_at)');
        $stmt->execute(array(
            ':user_id' => (int)$userId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
            ':created_at' => gmdate('c')
        ));
        return (int)$this->pdo->lastInsertId();
    }

    public function findValidByTokenHash($tokenHash)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM password_resets WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > :now LIMIT 1');
        $stmt->execute(array(':token_hash' => $tokenHash, ':now' => gmdate('c')));
        $row = $stmt->fetch();
        return $row ? $row : null;
    }

    public function markUsed($id)
    {
        $stmt = $this->pdo->prepare('UPDATE password_resets SET used_at = :used_at WHERE id = :id');
        $stmt->execute(array(':id' => (int)$id, ':used_at' => gmdate('c')));
    }

    public function invalidateForUser($userId)
    {
        $stmt = $this->pdo->prepare('UPDATE password_resets SET used_at = :used_at WHERE user_id = :user_id AND used_at IS NULL');
        $stmt->execute(array(':user_id' => (int)$userId, ':used_at' => gmdate('c')));
    }
}
