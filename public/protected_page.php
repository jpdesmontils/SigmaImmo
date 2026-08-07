<?php
require_once dirname(__DIR__) . '/app/Database/bootstrap.php';
require_once dirname(__DIR__) . '/app/Middleware/ApplicationAccessMiddleware.php';
sigma_db(); sigma_require_application_access();
$page = isset($_GET['page']) ? basename((string)$_GET['page']) : '';
$allowed = array('app.html','fiche-bien.html');
if (!in_array($page, $allowed, true) || !is_file(__DIR__ . '/' . $page)) { http_response_code(404); exit; }
header('Content-Type: text/html; charset=utf-8'); readfile(__DIR__ . '/' . $page);
