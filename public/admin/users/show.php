<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/Controllers/Web/Admin/UsersAdminController.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$flash = isset($_GET['flash']) ? (string)$_GET['flash'] : null;
$error = isset($_GET['error']) ? (string)$_GET['error'] : null;

(new UsersAdminController())->show($id, $flash, $error);
