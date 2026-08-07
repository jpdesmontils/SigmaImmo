<?php
$tmp = tempnam(sys_get_temp_dir(), 'sigma_blog_');
if ($tmp === false) {
    fwrite(STDERR, "Unable to create temp db\n");
    exit(1);
}
unlink($tmp);
putenv('SIGMAIMMO_SQLITE_PATH=' . $tmp);

require_once __DIR__ . '/../app/Database/bootstrap.php';
require_once __DIR__ . '/../app/Repositories/BlogArticleRepository.php';
require_once __DIR__ . '/../app/Services/BlogArticleService.php';
require_once __DIR__ . '/../app/Views/BlogContentRenderer.php';
require_once __DIR__ . '/../app/Seo/SeoMeta.php';
require_once __DIR__ . '/../app/Seo/Sitemap.php';

sigma_db();
$articles = new BlogArticleRepository(sigma_db());
$service = new BlogArticleService($articles);

// Creating a draft derives the slug from the title and defaults to unpublished.
$draft = $service->save(array(
    'title' => "Investir dans l'Ancien : Guide 2026",
    'meta_description' => str_repeat('a', 200),
    'excerpt' => 'Résumé',
    'content_blocks' => array(array('type' => 'paragraph', 'text' => 'Bonjour.'))
));
assertTrue($draft['slug'] === 'investir-dans-l-ancien-guide-2026', 'slug is derived and normalized from the title: ' . $draft['slug']);
assertTrue($draft['status'] === 'draft', 'article defaults to draft status');
assertTrue($draft['published_at'] === null, 'draft has no published_at');
assertTrue(strlen($draft['meta_description']) === BlogArticleService::META_DESCRIPTION_MAX_LENGTH, 'meta description is truncated to the max length');

// Publishing sets published_at; unpublishing clears it again.
$published = $service->setStatus($draft['id'], 'published');
assertTrue($published['status'] === 'published', 'article is published');
assertTrue($published['published_at'] !== null, 'publishing stamps published_at');

$unpublished = $service->setStatus($draft['id'], 'draft');
assertTrue($unpublished['status'] === 'draft', 'article is back to draft');
assertTrue($unpublished['published_at'] === null, 'unpublishing clears published_at');

// A duplicate slug is rejected.
$second = $service->save(array('title' => 'Un autre article', 'slug' => 'un-autre-article'));
$duplicateRejected = false;
try {
    $service->save(array('title' => 'Encore un autre', 'slug' => 'un-autre-article'));
} catch (InvalidArgumentException $e) {
    $duplicateRejected = $e->getMessage() === 'blog.slug_already_exists';
}
assertTrue($duplicateRejected, 'saving with an already-used slug is rejected');

// Only published articles are listed publicly and appear in the sitemap.
assertTrue(count($service->listPublished()) === 0, 'no published articles yet');
$service->setStatus($draft['id'], 'published');
$listed = $service->listPublished();
assertTrue(count($listed) === 1 && $listed[0]['slug'] === $draft['slug'], 'published article is listed publicly');
assertTrue($service->findPublishedBySlug($draft['slug']) !== null, 'published article is findable by slug');
assertTrue($service->findPublishedBySlug('un-autre-article') === null, 'draft article is not findable as published');

$sitemapPaths = array_map(function ($entry) {
    return $entry['path'];
}, (new Sitemap($articles))->entries());
assertTrue(in_array('/blog/' . $draft['slug'], $sitemapPaths, true), 'sitemap includes the published article');
assertTrue(!in_array('/blog/un-autre-article', $sitemapPaths, true), 'sitemap excludes draft articles');

// Content blocks render to escaped, safe HTML.
$renderer = new BlogContentRenderer();
$html = $renderer->render(array(
    array('type' => 'heading', 'text' => 'Titre <script>'),
    array('type' => 'paragraph', 'text' => 'Un paragraphe.'),
    array('type' => 'list', 'items' => array('Un', 'Deux')),
    array('type' => 'quote', 'text' => 'Citation.')
));
assertTrue(strpos($html, '<h2>Titre &lt;script&gt;</h2>') !== false, 'heading text is escaped');
assertTrue(strpos($html, '<p>Un paragraphe.</p>') !== false, 'paragraph is rendered');
assertTrue(strpos($html, '<li>Un</li>') !== false && strpos($html, '<li>Deux</li>') !== false, 'list items are rendered');
assertTrue(strpos($html, '<blockquote>Citation.</blockquote>') !== false, 'quote is rendered');

// SeoMeta builds a canonical URL, Open Graph tags and JSON-LD from a relative path.
putenv('SIGMAIMMO_BASE_URL=https://bienaufait.fr');
$seo = SeoMeta::build(array(
    'title' => 'Mon titre',
    'description' => 'Ma description',
    'path' => '/blog/mon-slug',
    'type' => 'article',
    'structured_data' => array('@type' => 'BlogPosting')
));
assertTrue($seo['canonical'] === 'https://bienaufait.fr/blog/mon-slug', 'canonical URL is absolute: ' . $seo['canonical']);
assertTrue($seo['title'] === 'Mon titre — BienAuFait', 'page title includes the brand suffix');
assertTrue($seo['og_type'] === 'article', 'og:type reflects the requested type');
assertTrue(strpos($seo['structured_data_json'], 'BlogPosting') !== false, 'structured data JSON is populated');
putenv('SIGMAIMMO_BASE_URL');

unlink($tmp);
echo "blog_article_test ok\n";

function assertTrue($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: " . $message . "\n");
        exit(1);
    }
}
