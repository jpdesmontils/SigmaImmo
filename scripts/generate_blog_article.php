<?php
require_once __DIR__ . '/../app/Database/bootstrap.php';
require_once __DIR__ . '/../app/Services/BlogGeneratorService.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script doit être lancé en CLI." . PHP_EOL);
    exit(1);
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/generate_blog_article.php \"sujet de l'article\"" . PHP_EOL);
    exit(1);
}

$topic = $argv[1];

sigma_db();

try {
    $article = (new BlogGeneratorService())->generateDraft($topic);
} catch (Exception $e) {
    fwrite(STDERR, 'Génération impossible : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo json_encode(array(
    'ok' => true,
    'id' => $article['id'],
    'slug' => $article['slug'],
    'title' => $article['title'],
    'status' => $article['status']
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
