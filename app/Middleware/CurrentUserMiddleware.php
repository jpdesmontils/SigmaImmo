<?php
require_once dirname(__DIR__) . '/Repositories/UserRepository.php';

function sigma_start_session()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function sigma_current_user()
{
    sigma_start_session();
    if (empty($_SESSION['user_id'])) return null;
    return (new UserRepository())->find((int)$_SESSION['user_id']);
}

function sigma_require_user()
{
    $user = sigma_current_user();
    if (!$user) {
        header('Location: /auth/login.php');
        exit;
    }
    return $user;
}
