<?php
require_once __DIR__ . '/../app/Database/bootstrap.php';
require_once __DIR__ . '/../app/Services/AuthService.php';
require_once __DIR__ . '/../app/Middleware/CurrentUserMiddleware.php';

sigma_db();
sigma_start_session();
$user = sigma_current_user();
(new AuthService())->logout($user);
$_SESSION = array();
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: /auth/login.php');
