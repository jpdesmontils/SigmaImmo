<?php
require_once dirname(__DIR__) . '/Repositories/UserRepository.php';
require_once dirname(__DIR__) . '/Repositories/PropertyRepository.php';
require_once dirname(__DIR__) . '/Repositories/AuditLogRepository.php';
require_once __DIR__ . '/PlanService.php';
require_once __DIR__ . '/AuditLogger.php';
require_once __DIR__ . '/AuthService.php';

/** Lecture/écriture des comptes utilisateurs pour le panneau admin. */
class UserAdminService
{
    const PER_PAGE = 20;

    private $users;
    private $properties;
    private $auditLogs;
    private $audit;
    private $auth;

    public function __construct(
        UserRepository $users = null,
        PropertyRepository $properties = null,
        AuditLogRepository $auditLogs = null,
        AuditLogger $audit = null,
        AuthService $auth = null
    ) {
        $this->users = $users ?: new UserRepository();
        $this->properties = $properties ?: new PropertyRepository();
        $this->auditLogs = $auditLogs ?: new AuditLogRepository();
        $this->audit = $audit ?: new AuditLogger();
        $this->auth = $auth ?: new AuthService();
    }

    public function listUsers($query, $plan, $page)
    {
        $query = trim((string)$query);
        $plan = in_array($plan, array('free', 'paid', 'admin'), true) ? $plan : '';
        $page = max(1, (int)$page);
        $total = $this->users->count($query, $plan);
        $totalPages = max(1, (int)ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);
        $offset = self::PER_PAGE * ($page - 1);

        $rows = $this->users->search($query, $plan, self::PER_PAGE, $offset);
        $self = $this;
        $items = array_map(function ($u) use ($self) {
            return array(
                'id' => $u['id'],
                'email' => $u['email'],
                'name' => $self->displayName($u),
                'initial' => $self->initialFor($u),
                'plan' => $u['plan'],
                'properties_count' => $self->properties->countActiveByUser($u['id']),
                'last_login_at' => $self->formatDateOrDash($u['last_login_at'], '—'),
                'created_at' => $self->formatDateOrDash($u['created_at'], '—')
            );
        }, $rows);

        return array(
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'total_pages' => $totalPages,
            'plan_counts' => $this->users->countByPlan()
        );
    }

    public function getUserDetail($id)
    {
        $user = $this->users->find($id);
        if (!$user) return null;

        $entries = array_map(array($this, 'formatAuditEntry'), $this->auditLogs->forUser($id, 15));

        return array(
            'user' => $user,
            'display_name' => $this->displayName($user),
            'initial' => $this->initialFor($user),
            'properties_count' => $this->properties->countActiveByUser($id),
            'favorites_limit' => PlanService::FREE_FAVORITES_LIMIT,
            'created_at' => $this->formatDateOrDash($user['created_at'], '—'),
            'last_login_at' => $this->formatDateOrDash($user['last_login_at'], 'Jamais'),
            'audit_entries' => $entries
        );
    }

    public function changePlan($id, $newPlan, $adminUser)
    {
        if (!in_array($newPlan, array('free', 'paid', 'admin'), true)) {
            throw new InvalidArgumentException('user.invalid_plan');
        }
        $user = $this->users->find($id);
        if (!$user) {
            throw new InvalidArgumentException('user.not_found');
        }
        if ((int)$id === (int)$adminUser['id'] && $newPlan !== 'admin') {
            throw new InvalidArgumentException('user.cannot_demote_self');
        }

        $previousPlan = $user['plan'];
        $this->users->updatePlan($id, $newPlan);
        $this->audit->log('admin.user.plan_changed', $id, null, array(
            'changed_by' => (int)$adminUser['id'],
            'from' => $previousPlan,
            'to' => $newPlan
        ));
    }

    public function triggerPasswordReset($id, $resetUrlTemplate, $adminUser)
    {
        $user = $this->users->find($id);
        if (!$user) {
            throw new InvalidArgumentException('user.not_found');
        }
        $this->auth->requestPasswordReset($user['email'], $resetUrlTemplate);
        $this->audit->log('admin.user.password_reset_triggered', $id, null, array(
            'triggered_by' => (int)$adminUser['id']
        ));
    }

    public function displayName($user)
    {
        if (!empty($user['name'])) return $user['name'];
        $email = isset($user['email']) ? (string)$user['email'] : '';
        $at = strpos($email, '@');
        return $at !== false ? substr($email, 0, $at) : $email;
    }

    public function initialFor($user)
    {
        $label = $this->displayName($user);
        return $label !== '' ? mb_strtoupper(mb_substr($label, 0, 1)) : '?';
    }

    public function formatDateOrDash($value, $fallback)
    {
        if (!$value) return $fallback;
        return str_replace('T', ' ', substr((string)$value, 0, 16));
    }

    private function formatAuditEntry($row)
    {
        return array(
            'action' => $row['action'],
            'created_at' => $this->formatDateOrDash($row['created_at'], '—')
        );
    }
}
