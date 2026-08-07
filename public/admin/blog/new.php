<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/Controllers/Web/Admin/BlogAdminController.php';

(new BlogAdminController())->form();
