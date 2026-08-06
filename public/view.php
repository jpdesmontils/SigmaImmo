<?php
require_once dirname(__DIR__) . '/app/Database/bootstrap.php';
require_once dirname(__DIR__) . '/app/Middleware/ApplicationAccessMiddleware.php';

sigma_db();
sigma_require_application_access();

$template = isset($_GET['template']) ? (string)$_GET['template'] : '';
$allowed = array(
    'fiche-investissement-locatif',
    'fiche-investissement-mdb',
    'fiche-investissement-patrimonial'
);

if (!in_array($template, $allowed, true)) {
    http_response_code(404);
    exit;
}

$path = dirname(__DIR__) . '/app/Views/' . $template . '.html';
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
readfile($path);
