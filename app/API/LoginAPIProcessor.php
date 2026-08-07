<?php
require_once __DIR__ . '/APIException.php';
require_once dirname(__DIR__) . '/Services/AuthService.php';
require_once dirname(__DIR__) . '/Services/ApiTokenService.php';
require_once dirname(__DIR__) . '/Repositories/ApiTokenRepository.php';

class LoginAPIProcessor
{
    private $auth;
    private $tokens;

    public function __construct(PDO $pdo, array $route, array $user, array $params = array())
    {
        $this->auth = new AuthService();
        $this->tokens = new ApiTokenService(new ApiTokenRepository($pdo));
    }

    public function process($method, $body)
    {
        $email = is_array($body) && isset($body['email']) ? trim((string)$body['email']) : '';
        $password = is_array($body) && isset($body['password']) ? (string)$body['password'] : '';
        if ($email === '' || $password === '') {
            throw new APIException(422, 'auth.credentials_required', 'Identifiant et mot de passe requis.');
        }
        try {
            $user = $this->auth->login($email, $password);
        } catch (InvalidArgumentException $e) {
            throw new APIException(401, 'auth.invalid_credentials', 'Identifiants incorrects.');
        }
        $issued = $this->tokens->issue($user['id'], 'Extension Chrome');
        return array('data' => array('token' => $issued['token']), 'meta' => array('notice' => 'Ce token ne sera plus affiché.'));
    }
}
