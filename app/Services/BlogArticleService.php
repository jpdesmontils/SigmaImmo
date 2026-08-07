<?php
require_once dirname(__DIR__) . '/Repositories/BlogArticleRepository.php';
require_once __DIR__ . '/AuditLogger.php';

class BlogArticleService
{
    const META_DESCRIPTION_MAX_LENGTH = 160;

    private $articles;
    private $audit;

    public function __construct(BlogArticleRepository $articles = null, AuditLogger $audit = null)
    {
        $this->articles = $articles ?: new BlogArticleRepository();
        $this->audit = $audit ?: new AuditLogger();
    }

    public function listForAdmin()
    {
        return $this->articles->allForAdmin();
    }

    public function listPublished($limit = null)
    {
        return $this->articles->allPublished($limit);
    }

    public function find($id)
    {
        return $this->articles->find($id);
    }

    public function findPublishedBySlug($slug)
    {
        return $this->articles->findPublishedBySlug($slug);
    }

    public function slugify($text)
    {
        $text = strtolower(trim((string)$text));
        $text = strtr($text, array('à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c'));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }

    /**
     * Crée ou met à jour un article. $input attend : title, slug, meta_description,
     * canonical_url, excerpt, content_blocks (array), cover_image_url, author, status.
     */
    public function save($input, $id = null, $userId = null)
    {
        $title = trim((string)(isset($input['title']) ? $input['title'] : ''));
        if ($title === '') {
            throw new InvalidArgumentException('blog.title_required');
        }
        $slug = trim((string)(isset($input['slug']) ? $input['slug'] : ''));
        $slug = $slug !== '' ? $this->slugify($slug) : $this->slugify($title);
        if ($slug === '') {
            throw new InvalidArgumentException('blog.slug_required');
        }
        if ($this->articles->slugExists($slug, $id)) {
            throw new InvalidArgumentException('blog.slug_already_exists');
        }

        $metaDescription = trim((string)(isset($input['meta_description']) ? $input['meta_description'] : ''));
        if (function_exists('mb_substr')) {
            $metaDescription = mb_substr($metaDescription, 0, self::META_DESCRIPTION_MAX_LENGTH);
        } else {
            $metaDescription = substr($metaDescription, 0, self::META_DESCRIPTION_MAX_LENGTH);
        }

        $contentBlocks = isset($input['content_blocks']) && is_array($input['content_blocks']) ? $input['content_blocks'] : array();
        $status = isset($input['status']) && $input['status'] === 'published' ? 'published' : 'draft';

        $existing = $id !== null ? $this->articles->find($id) : null;
        $publishedAt = $existing ? $existing['published_at'] : null;
        if ($status === 'published' && !$publishedAt) {
            $publishedAt = gmdate('c');
        }
        if ($status === 'draft') {
            $publishedAt = null;
        }

        $canonicalUrl = trim((string)(isset($input['canonical_url']) ? $input['canonical_url'] : ''));
        if ($canonicalUrl === '') {
            $canonicalUrl = '/blog/' . $slug;
        }

        $fields = array(
            'slug' => $slug,
            'title' => $title,
            'meta_description' => $metaDescription,
            'canonical_url' => $canonicalUrl,
            'status' => $status,
            'excerpt' => trim((string)(isset($input['excerpt']) ? $input['excerpt'] : '')),
            'content_json' => json_encode($contentBlocks, JSON_UNESCAPED_UNICODE),
            'cover_image_url' => trim((string)(isset($input['cover_image_url']) ? $input['cover_image_url'] : '')),
            'author' => trim((string)(isset($input['author']) ? $input['author'] : '')),
            'generated_by_llm' => $existing ? $existing['generated_by_llm'] : !empty($input['generated_by_llm']),
            'published_at' => $publishedAt
        );

        if ($existing) {
            $article = $this->articles->update($id, $fields);
            $this->audit->log('blog.article.updated', $userId, null, array('id' => $article['id'], 'slug' => $article['slug']));
        } else {
            $article = $this->articles->create($fields);
            $this->audit->log('blog.article.created', $userId, null, array('id' => $article['id'], 'slug' => $article['slug']));
        }
        return $article;
    }

    public function setStatus($id, $status, $userId = null)
    {
        $article = $this->articles->find($id);
        if (!$article) {
            throw new InvalidArgumentException('blog.article_not_found');
        }
        $status = $status === 'published' ? 'published' : 'draft';
        $fields = $article;
        $fields['status'] = $status;
        $fields['content_json'] = json_encode($article['content_blocks'], JSON_UNESCAPED_UNICODE);
        $fields['published_at'] = $status === 'published' ? ($article['published_at'] ?: gmdate('c')) : null;
        $updated = $this->articles->update($id, $fields);
        $this->audit->log($status === 'published' ? 'blog.article.published' : 'blog.article.unpublished', $userId, null, array('id' => $id, 'slug' => $article['slug']));
        return $updated;
    }

    public function delete($id, $userId = null)
    {
        $article = $this->articles->find($id);
        if (!$article) {
            return false;
        }
        $deleted = $this->articles->delete($id);
        if ($deleted) {
            $this->audit->log('blog.article.deleted', $userId, null, array('id' => $id, 'slug' => $article['slug']));
        }
        return $deleted;
    }
}
