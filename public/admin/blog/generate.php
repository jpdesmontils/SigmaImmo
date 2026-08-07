<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/Controllers/Web/Admin/BlogAdminController.php';

$controller = new BlogAdminController();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->generate($_POST, $adminUser['id']);
} else {
    $controller->generateForm();
}
