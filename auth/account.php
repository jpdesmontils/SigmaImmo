<?php
require_once __DIR__ . '/../app/Database/bootstrap.php';
require_once __DIR__ . '/../app/Middleware/CurrentUserMiddleware.php';
require_once __DIR__ . '/../app/Repositories/PropertyRepository.php';
require_once __DIR__ . '/../app/Services/PlanService.php';
require_once __DIR__ . '/_view.php';

sigma_db();
$user = sigma_require_user();
$labels = auth_labels()->section('auth.account');
$repo = new PropertyRepository(sigma_db());
$count = $repo->countActiveByUser($user['id']);
$limit = $user['plan'] === 'free' ? ' / ' . PlanService::FREE_FAVORITES_LIMIT : ' ' . (isset($labels['unlimited']) ? $labels['unlimited'] : '');

auth_render_template('app/auth/account', array(
    'page_title' => isset($labels['title']) ? $labels['title'] : '',
    'labels' => $labels,
    'email' => $user['email'],
    'plan' => $user['plan'],
    'favorites_count' => (int)$count,
    'favorites_limit' => $limit
));
