<?php
require_once dirname(dirname(dirname(__DIR__))) . '/Views/TemplateRenderer.php';
require_once dirname(dirname(dirname(__DIR__))) . '/Services/UserAdminService.php';

/** Panneau admin de gestion des utilisateurs (réservé aux comptes plan=admin). */
class UsersAdminController
{
    private $renderer;
    private $service;

    private static $flashLabels = array(
        'plan_updated' => 'Plan mis à jour.',
        'reset_sent' => "Email de réinitialisation envoyé à l'utilisateur."
    );

    private static $errorLabels = array(
        'user.invalid_plan' => 'Plan invalide.',
        'user.cannot_demote_self' => 'Vous ne pouvez pas retirer votre propre accès administrateur.',
        'user.not_found' => 'Utilisateur introuvable.'
    );

    public function __construct(TemplateRenderer $renderer = null, UserAdminService $service = null)
    {
        $this->renderer = $renderer ?: new TemplateRenderer();
        $this->service = $service ?: new UserAdminService();
    }

    public function index($query, $plan, $page, $flashCode = null)
    {
        $result = $this->service->listUsers($query, $plan, $page);
        $plan = in_array($plan, array('free', 'paid', 'admin'), true) ? $plan : '';

        $this->render('admin/users/index', array(
            'page_title' => 'Utilisateurs',
            'flash' => $flashCode && isset(self::$flashLabels[$flashCode]) ? self::$flashLabels[$flashCode] : null,
            'query' => $query,
            'query_url' => rawurlencode($query),
            'plan_filter' => $plan,
            'is_all' => $plan === '',
            'is_free' => $plan === 'free',
            'is_paid' => $plan === 'paid',
            'is_admin' => $plan === 'admin',
            'users' => $result['items'],
            'total' => $result['total'],
            'plan_counts' => $result['plan_counts'],
            'page' => $result['page'],
            'total_pages' => $result['total_pages'],
            'has_prev' => $result['page'] > 1,
            'has_next' => $result['page'] < $result['total_pages'],
            'prev_page' => max(1, $result['page'] - 1),
            'next_page' => min($result['total_pages'], $result['page'] + 1)
        ));
    }

    public function show($id, $flashCode = null, $errorCode = null)
    {
        $detail = $this->service->getUserDetail($id);
        if (!$detail) {
            http_response_code(404);
            echo 'Utilisateur introuvable.';
            return;
        }

        $user = $detail['user'];
        $planOptions = array();
        foreach (array('free', 'paid', 'admin') as $p) {
            $planOptions[] = array('value' => $p, 'label' => ucfirst($p), 'selected' => $p === $user['plan']);
        }

        $this->render('admin/users/show', array(
            'page_title' => $detail['display_name'],
            'flash' => $flashCode && isset(self::$flashLabels[$flashCode]) ? self::$flashLabels[$flashCode] : null,
            'has_error' => $errorCode !== null,
            'error' => $errorCode && isset(self::$errorLabels[$errorCode]) ? self::$errorLabels[$errorCode] : $errorCode,
            'user' => array(
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $detail['display_name'],
                'initial' => $detail['initial'],
                'plan' => $user['plan'],
                'created_at' => $detail['created_at'],
                'last_login_at' => $detail['last_login_at']
            ),
            'plan_options' => $planOptions,
            'properties_count' => $detail['properties_count'],
            'favorites_limit' => $detail['favorites_limit'],
            'is_free_plan' => $user['plan'] === 'free',
            'audit_entries' => $detail['audit_entries']
        ));
    }

    public function updatePlan($id, $newPlan, $adminUser)
    {
        try {
            $this->service->changePlan($id, $newPlan, $adminUser);
        } catch (InvalidArgumentException $e) {
            header('Location: /admin/users/show.php?id=' . (int)$id . '&error=' . rawurlencode($e->getMessage()));
            exit;
        }
        header('Location: /admin/users/show.php?id=' . (int)$id . '&flash=plan_updated');
        exit;
    }

    public function sendReset($id, $resetUrlTemplate, $adminUser)
    {
        try {
            $this->service->triggerPasswordReset($id, $resetUrlTemplate, $adminUser);
        } catch (InvalidArgumentException $e) {
            header('Location: /admin/users/show.php?id=' . (int)$id . '&error=' . rawurlencode($e->getMessage()));
            exit;
        }
        header('Location: /admin/users/show.php?id=' . (int)$id . '&flash=reset_sent');
        exit;
    }

    private function render($template, array $context)
    {
        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderer->renderWithLayout($template, 'layouts/admin', $context);
    }
}
