<?php
require_once __DIR__ . '/APIException.php';
class MeAPIProcessor
{
    private $user;
    public function __construct(PDO $pdo, array $route, array $user, array $params = array()) { $this->user = $user; }
    public function process($method, $body)
    {
        if ($method !== 'GET') throw new APIException(405, 'method.not_allowed', 'Méthode non autorisée.');
        return array('data' => array('email' => $this->user['email'], 'plan' => $this->user['plan']), 'meta' => array());
    }
}
