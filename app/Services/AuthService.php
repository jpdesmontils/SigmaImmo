<?php
require_once dirname(__DIR__) . '/Repositories/UserRepository.php';
require_once __DIR__ . '/AuditLogger.php';
require_once __DIR__ . '/PlanService.php';

class AuthService
{
    private $users;
    private $audit;
    private $plans;

    public function __construct(UserRepository $users = null, AuditLogger $audit = null, PlanService $plans = null)
    {
        $this->users = $users ?: new UserRepository();
        $this->audit = $audit ?: new AuditLogger();
        $this->plans = $plans ?: new PlanService();
    }

    public function register($email, $password, $name = '')
    {
        $email = trim((string)$email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('auth.invalid_email');
        if (strlen((string)$password) < 8) throw new InvalidArgumentException('auth.password_too_short');
        if ($this->users->findByEmail($email)) throw new InvalidArgumentException('auth.email_already_exists');
        $user = $this->users->create($email, password_hash($password, PASSWORD_DEFAULT), 'free', trim((string)$name));
        $this->audit->log('auth.register', $user['id'], null, array('email' => $email));
        return $user;
    }

    public function login($email, $password)
    {
        $email = trim((string)$email);
        $user = $this->users->findByEmail($email);
        if (!$user || empty($user['password_hash']) || !password_verify((string)$password, $user['password_hash'])) {
            $this->audit->log('auth.login.failure', null, null, array('email' => $email));
            throw new InvalidArgumentException('auth.invalid_credentials');
        }
        $this->users->touchLastLogin($user['id']);
        $this->audit->log('auth.login.success', $user['id'], null, array('email' => $email));
        return $this->users->find($user['id']);
    }

    public function logout($user)
    {
        $this->audit->log('auth.logout', isset($user['id']) ? $user['id'] : null);
    }
}
