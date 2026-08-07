<?php
require_once dirname(__DIR__) . '/app/Database/bootstrap.php';
require_once dirname(__DIR__) . '/app/Seo/Sitemap.php';

sigma_db();

header('Content-Type: application/xml; charset=utf-8');
echo (new Sitemap())->toXml();
