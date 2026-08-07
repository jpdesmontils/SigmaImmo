<?php
require_once dirname(dirname(__DIR__)) . '/app/Database/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/Controllers/Web/BlogController.php';

sigma_db();
$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
(new BlogController())->show($slug);
