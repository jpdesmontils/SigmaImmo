<?php
require_once dirname(__DIR__) . '/Services/PlanService.php';

function sigma_require_admin($user)
{
    $plans = new PlanService();
    if (!$plans->isAdmin($user)) {
        http_response_code(403);
        echo 'Accès réservé aux administrateurs.';
        exit;
    }
}
