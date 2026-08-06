<?php
require_once __DIR__ . '/APIException.php';
require_once dirname(__DIR__) . '/Services/ApiTokenService.php';
require_once dirname(__DIR__) . '/Repositories/ApiTokenRepository.php';
class TokenAPIProcessor
{
    private $user; private $tokens; private $service;
    public function __construct(PDO $pdo, array $route, array $user, array $params = array()) { $this->user = $user; $this->tokens = new ApiTokenRepository($pdo); $this->service = new ApiTokenService($this->tokens); }
    public function process($method, $body)
    {
        if ($method === 'GET') return array('data' => $this->tokens->allForUser($this->user['id']), 'meta' => array());
        if ($method === 'POST') { $label = is_array($body) && isset($body['label']) ? trim((string)$body['label']) : 'Compte web'; return array('data' => $this->service->issue($this->user['id'], $label), 'meta' => array('notice' => 'Ce token ne sera plus affiché.')); }
        throw new APIException(405, 'method.not_allowed', 'Méthode non autorisée.');
    }
}
