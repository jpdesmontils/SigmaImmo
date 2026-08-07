<?php
require_once dirname(dirname(__DIR__)) . '/Views/TemplateRenderer.php';
require_once dirname(dirname(__DIR__)) . '/Views/BlogContentRenderer.php';
require_once dirname(dirname(__DIR__)) . '/Services/BlogArticleService.php';
require_once dirname(dirname(__DIR__)) . '/Seo/SeoMeta.php';

/** Rend le blog public : liste des articles publiés et fiche article. */
class BlogController
{
    private $renderer;
    private $articles;
    private $contentRenderer;

    public function __construct(TemplateRenderer $renderer = null, BlogArticleService $articles = null, BlogContentRenderer $contentRenderer = null)
    {
        $this->renderer = $renderer ?: new TemplateRenderer();
        $this->articles = $articles ?: new BlogArticleService();
        $this->contentRenderer = $contentRenderer ?: new BlogContentRenderer();
    }

    public function index()
    {
        $items = array_map(function ($article) {
            return array(
                'slug' => $article['slug'],
                'title' => $article['title'],
                'excerpt' => $article['excerpt'],
                'published_date' => substr((string)$article['published_at'], 0, 10)
            );
        }, $this->articles->listPublished());

        $seo = SeoMeta::build(array(
            'title' => 'Blog BienAuFait — investissement immobilier',
            'description' => "Analyses, méthodes et retours d'expérience sur l'investissement locatif, le marchand de biens et l'analyse de rentabilité immobilière.",
            'path' => '/blog'
        ));

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderer->renderWithLayout('public/blog/index', 'layouts/public', array(
            'seo' => $seo,
            'articles' => $items
        ));
    }

    public function show($slug)
    {
        $article = $this->articles->findPublishedBySlug($slug);
        if (!$article) {
            $this->renderNotFound();
            return;
        }

        $structuredData = array(
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $article['title'],
            'description' => $article['meta_description'],
            'datePublished' => $article['published_at'],
            'dateModified' => $article['updated_at'],
            'author' => array('@type' => 'Organization', 'name' => $article['author'] !== '' ? $article['author'] : 'BienAuFait'),
            'mainEntityOfPage' => SeoMeta::absoluteUrl('/blog/' . $article['slug'])
        );
        if ($article['cover_image_url'] !== '') {
            $structuredData['image'] = SeoMeta::absoluteUrl($article['cover_image_url']);
        }

        $seo = SeoMeta::build(array(
            'title' => $article['title'],
            'description' => $article['meta_description'],
            'path' => $article['canonical_url'] !== '' ? $article['canonical_url'] : '/blog/' . $article['slug'],
            'type' => 'article',
            'image' => $article['cover_image_url'],
            'structured_data' => $structuredData
        ));

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderer->renderWithLayout('public/blog/article', 'layouts/public', array(
            'seo' => $seo,
            'title' => $article['title'],
            'author' => $article['author'],
            'published_date' => substr((string)$article['published_at'], 0, 10),
            'content_html' => $this->contentRenderer->render($article['content_blocks'])
        ));
    }

    private function renderNotFound()
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderer->renderWithLayout('public/blog/not-found', 'layouts/public', array(
            'seo' => SeoMeta::build(array('title' => 'Article introuvable', 'description' => 'Cet article n\'existe pas ou n\'est plus publié.', 'path' => '/blog'))
        ));
    }
}
