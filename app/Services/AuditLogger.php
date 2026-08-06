<?php
require_once dirname(__DIR__) . '/Database/Connection.php';
require_once dirname(dirname(__DIR__)) . '/api/logger.php';

class AuditLogger
{
    private $pdo;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?: DatabaseConnection::get();
    }

    public function log($action, $userId = null, $propertyId = null, $payload = array())
    {
        $stmt = $this->pdo->prepare('INSERT INTO audit_logs (user_id, property_id, action, payload_json, ip_address, user_agent, created_at) VALUES (:user_id, :property_id, :action, :payload_json, :ip_address, :user_agent, :created_at)');
        $stmt->execute(array(
            ':user_id' => $userId === null ? null : (int)$userId,
            ':property_id' => $propertyId,
            ':action' => $action,
            ':payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ':ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
            ':user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
            ':created_at' => gmdate('c')
        ));
        appLog('app', $action, array(
            'user_id' => $userId === null ? null : (int)$userId,
            'property_id' => $propertyId,
            'payload' => $payload
        ));
    }
}
