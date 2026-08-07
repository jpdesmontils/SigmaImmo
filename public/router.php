<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (strpos($path, '/api/v1') === 0) { require __DIR__ . '/api/index.php'; return true; }
if (preg_match('#^/api/[^/]+\.php$#', $path)) { http_response_code(410); return true; }
$protected = array('/app.html','/fiche-bien.html');
if (in_array($path, $protected, true)) { $_GET['page'] = ltrim($path, '/'); require __DIR__ . '/protected_page.php'; return true; }
if ($path === '/sitemap.xml') { require __DIR__ . '/sitemap.php'; return true; }
if ($path === '/investissement-locatif') { require __DIR__ . '/investissement-locatif.php'; return true; }
if ($path === '/marchand-de-biens') { require __DIR__ . '/marchand-de-biens.php'; return true; }
if ($path === '/analyse-rentabilite') { require __DIR__ . '/analyse-rentabilite.php'; return true; }
if ($path === '/guides') { require __DIR__ . '/guides.php'; return true; }
if ($path === '/blog' || $path === '/blog/') { require __DIR__ . '/blog/index.php'; return true; }
if (preg_match('#^/blog/([a-z0-9-]+)$#', $path, $match)) { $_GET['slug'] = $match[1]; require __DIR__ . '/blog/article.php'; return true; }
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) return false;
return false;
