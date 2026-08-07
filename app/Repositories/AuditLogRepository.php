<?php
require_once dirname(__DIR__) . '/Database/Connection.php';

class AuditLogRepository
{
    private $pdo;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?: DatabaseConnection::get();
    }

    public function forUser($userId, $limit = 15)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM audit_logs WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':user_id', (int)$userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
