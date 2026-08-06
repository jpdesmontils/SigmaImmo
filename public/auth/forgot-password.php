<?php
$root = dirname(dirname(__DIR__));
require_once $root . '/app/Database/bootstrap.php';
require_once $root . '/app/Services/AuthService.php';
require_once $root . '/app/Middleware/CurrentUserMiddleware.php';
require_once __DIR__ . '/_view.php';

sigma_db();
sigma_start_session();
$labels = auth_labels()->section('auth.forgot_password');
$email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $resetUrlTemplate = $scheme . '://' . $host . '/auth/reset-password.php?token=%s';
    (new AuthService())->requestPasswordReset($email, $resetUrlTemplate);
    $submitted = true;
}

auth_render_template('app/auth/forgot-password', array(
    'page_title' => isset($labels['title']) ? $labels['title'] : '',
    'labels' => $labels,
    'email' => $email,
    'submitted' => $submitted
));
