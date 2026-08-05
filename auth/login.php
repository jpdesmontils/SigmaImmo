<?php
require_once __DIR__ . '/../app/Database/bootstrap.php';
require_once __DIR__ . '/../app/Services/AuthService.php';
require_once __DIR__ . '/../app/Middleware/CurrentUserMiddleware.php';
require_once __DIR__ . '/_view.php';

sigma_db();
sigma_start_session();
$labels = auth_labels()->section('auth.login');
$error = '';
$email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $auth = new AuthService();
        $user = $auth->login($email, isset($_POST['password']) ? $_POST['password'] : '');
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        header('Location: /auth/account.php');
        exit;
    } catch (Exception $e) {
        $error = auth_error_label($e->getMessage());
    }
}

auth_render_template('app/auth/login', array(
    'page_title' => isset($labels['title']) ? $labels['title'] : '',
    'labels' => $labels,
    'has_error' => $error !== '',
    'error' => $error,
    'email' => $email
));
