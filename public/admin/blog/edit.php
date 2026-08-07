<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/Controllers/Web/Admin/BlogAdminController.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
(new BlogAdminController())->form($id ?: null);
