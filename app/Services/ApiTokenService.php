<?php
require_once dirname(__DIR__) . '/Repositories/ApiTokenRepository.php';

class ApiTokenService
{
    private $tokens;
    public function __construct(ApiTokenRepository $tokens = null) { $this->tokens = $tokens ?: new ApiTokenRepository(); }

    public function issue($userId, $label, $expiresAt = null)
    {
        $secret = bin2hex(random_bytes(32));
        $id = $this->tokens->create($userId, hash('sha256', $secret), $label, $expiresAt);
        return array('id' => $id, 'token' => $secret);
    }

    public function authenticate($secret)
    {
        if (!is_string($secret) || !preg_match('/^[a-f0-9]{64}$/', $secret)) return null;
        $row = $this->tokens->findValidByHash(hash('sha256', $secret));
        if (!$row) return null;
        $this->tokens->touch($row['id']);
        return array('id' => (int)$row['user_id'], 'email' => $row['email'], 'name' => $row['name'], 'plan' => $row['plan'], 'token_id' => (int)$row['id']);
    }
}
