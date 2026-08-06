<?php
require_once dirname(__DIR__) . '/Repositories/UserRepository.php';
require_once dirname(__DIR__) . '/Repositories/PasswordResetRepository.php';
require_once __DIR__ . '/AuditLogger.php';
require_once __DIR__ . '/PlanService.php';
require_once __DIR__ . '/MailerService.php';

class AuthService
{
    const RESET_TOKEN_TTL_SECONDS = 3600;

    private $users;
    private $audit;
    private $plans;
    private $resets;
    private $mailer;

    public function __construct(UserRepository $users = null, AuditLogger $audit = null, PlanService $plans = null, PasswordResetRepository $resets = null, MailerService $mailer = null)
    {
        $this->users = $users ?: new UserRepository();
        $this->audit = $audit ?: new AuditLogger();
        $this->plans = $plans ?: new PlanService();
        $this->resets = $resets ?: new PasswordResetRepository();
        $this->mailer = $mailer ?: new MailerService();
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

    /**
     * Génère un token de réinitialisation et envoie l'email correspondant.
     * $resetUrlTemplate doit contenir un "%s" remplacé par le token en clair.
     * Ne révèle jamais si l'email existe : retourne toujours normalement.
     */
    public function requestPasswordReset($email, $resetUrlTemplate)
    {
        $email = trim((string)$email);
        $user = $this->users->findByEmail($email);
        if (!$user) {
            $this->audit->log('auth.password_reset.requested_unknown', null, null, array('email' => $email));
            return;
        }
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = gmdate('c', time() + self::RESET_TOKEN_TTL_SECONDS);
        $this->resets->invalidateForUser($user['id']);
        $this->resets->create($user['id'], $tokenHash, $expiresAt);
        $resetUrl = sprintf($resetUrlTemplate, $token);
        $this->mailer->send(
            $user['email'],
            'Réinitialisation de votre mot de passe SigmaImmo',
            "Bonjour,\n\nVous avez demandé la réinitialisation de votre mot de passe.\n"
            . "Ce lien est valable 1 heure : " . $resetUrl . "\n\n"
            . "Si vous n'êtes pas à l'origine de cette demande, ignorez cet email."
        );
        $this->audit->log('auth.password_reset.requested', $user['id']);
    }

    public function resetPassword($token, $newPassword)
    {
        $token = trim((string)$token);
        if ($token === '') {
            throw new InvalidArgumentException('auth.invalid_reset_token');
        }
        if (strlen((string)$newPassword) < 8) {
            throw new InvalidArgumentException('auth.password_too_short');
        }
        $reset = $this->resets->findValidByTokenHash(hash('sha256', $token));
        if (!$reset) {
            throw new InvalidArgumentException('auth.invalid_reset_token');
        }
        $this->users->updatePasswordHash($reset['user_id'], password_hash($newPassword, PASSWORD_DEFAULT));
        $this->resets->markUsed($reset['id']);
        $this->audit->log('auth.password_reset.completed', $reset['user_id']);
        return $this->users->find($reset['user_id']);
    }
}
