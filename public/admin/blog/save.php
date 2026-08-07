<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/Controllers/Web/Admin/BlogAdminController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
(new BlogAdminController())->save($id, $_POST, $adminUser['id']);
