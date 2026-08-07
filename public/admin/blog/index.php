<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/Controllers/Web/Admin/BlogAdminController.php';

$flash = isset($_GET['flash']) ? (string)$_GET['flash'] : null;
(new BlogAdminController())->index($flash);
