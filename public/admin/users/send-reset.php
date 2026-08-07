<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/Controllers/Web/Admin/UsersAdminController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$resetUrlTemplate = $scheme . '://' . $host . '/auth/reset-password.php?token=%s';
(new UsersAdminController())->sendReset($id, $resetUrlTemplate, $adminUser);
