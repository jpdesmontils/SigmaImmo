<?php
$root = dirname(dirname(__DIR__));
require_once $root . '/app/Database/bootstrap.php';
require_once $root . '/app/Services/AuthService.php';
require_once $root . '/app/Middleware/CurrentUserMiddleware.php';
require_once __DIR__ . '/_view.php';

sigma_db();
sigma_start_session();
$labels = auth_labels()->section('auth.reset_password');
$token = isset($_GET['token']) ? (string)$_GET['token'] : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['token']) ? (string)$_POST['token'] : $token;
}
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $passwordConfirm = isset($_POST['password_confirm']) ? (string)$_POST['password_confirm'] : '';
    try {
        if ($password !== $passwordConfirm) {
            throw new InvalidArgumentException('auth.password_mismatch');
        }
        (new AuthService())->resetPassword($token, $password);
        $success = true;
    } catch (Exception $e) {
        $error = auth_error_label($e->getMessage());
    }
}

auth_render_template('app/auth/reset-password', array(
    'page_title' => isset($labels['title']) ? $labels['title'] : '',
    'labels' => $labels,
    'token' => $token,
    'has_error' => $error !== '',
    'error' => $error,
    'success' => $success
));
