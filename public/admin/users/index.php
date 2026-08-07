<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/Controllers/Web/Admin/UsersAdminController.php';

$query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$plan = isset($_GET['plan']) ? (string)$_GET['plan'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$flash = isset($_GET['flash']) ? (string)$_GET['flash'] : null;

(new UsersAdminController())->index($query, $plan, $page, $flash);
