<?php
$root = dirname(dirname(__DIR__));
require_once $root . '/app/Database/bootstrap.php';
require_once $root . '/app/Middleware/CurrentUserMiddleware.php';
require_once $root . '/app/Repositories/PropertyRepository.php';
require_once $root . '/app/Services/PlanService.php';
require_once $root . '/app/Services/ApiTokenService.php';
require_once $root . '/app/Repositories/ApiTokenRepository.php';
require_once __DIR__ . '/_view.php';

sigma_db();
$user = sigma_require_user();
$labels = auth_labels()->section('auth.account');
$repo = new PropertyRepository(sigma_db());
$count = $repo->countActiveByUser($user['id']);
$limit = $user['plan'] === 'free' ? ' / ' . PlanService::FREE_FAVORITES_LIMIT : ' ' . (isset($labels['unlimited']) ? $labels['unlimited'] : '');
$tokenRepository = new ApiTokenRepository(sigma_db());
$issuedToken = '';
sigma_start_session();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) { http_response_code(403); exit('Jeton CSRF invalide.'); }
    $issued = (new ApiTokenService($tokenRepository))->issue($user['id'], isset($_POST['token_label']) ? $_POST['token_label'] : 'Compte web');
    $issuedToken = $issued['token'];
}

auth_render_template('app/auth/account', array(
    'page_title' => isset($labels['title']) ? $labels['title'] : '',
    'labels' => $labels,
    'email' => $user['email'],
    'plan' => $user['plan'],
    'favorites_count' => (int)$count,
    'favorites_limit' => $limit,
    'has_issued_token' => $issuedToken !== '',
    'issued_token' => $issuedToken,
    'csrf_token' => $_SESSION['csrf_token'],
    'tokens' => $tokenRepository->allForUser($user['id'])
));
