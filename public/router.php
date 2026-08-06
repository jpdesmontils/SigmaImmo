<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (strpos($path, '/api/v1') === 0) { require __DIR__ . '/api/index.php'; return true; }
if (preg_match('#^/api/[^/]+\.php$#', $path)) { http_response_code(410); return true; }
$protected = array('/app.html','/fiche-bien.html','/guide-investissement-locatif.html','/guide-mdb-division-parcellaire.html');
if (in_array($path, $protected, true)) { $_GET['page'] = ltrim($path, '/'); require __DIR__ . '/protected_page.php'; return true; }
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) return false;
return false;
