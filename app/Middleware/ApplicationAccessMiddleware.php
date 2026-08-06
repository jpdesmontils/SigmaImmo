<?php
require_once __DIR__ . '/CurrentUserMiddleware.php';
require_once dirname(__DIR__) . '/Services/ApiTokenService.php';

function sigma_application_user()
{
    $user = sigma_current_user(); if ($user) return $user;
    $header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (preg_match('/^Bearer\s+(.+)$/i', $header, $match)) return (new ApiTokenService())->authenticate(trim($match[1]));
    $token = isset($_GET['access_token']) ? trim((string)$_GET['access_token']) : '';
    return $token !== '' ? (new ApiTokenService())->authenticate($token) : null;
}

function sigma_require_application_access()
{
    $user = sigma_application_user();
    if (!$user) { header('Location: /auth/login.php'); exit; }
    return $user;
}
