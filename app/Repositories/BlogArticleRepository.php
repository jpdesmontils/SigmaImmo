<?php
require_once dirname(__DIR__) . '/Database/Connection.php';

class BlogArticleRepository
{
    private $pdo;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?: DatabaseConnection::get();
    }

    public function allForAdmin()
    {
        $rows = $this->pdo->query('SELECT * FROM blog_articles ORDER BY updated_at DESC')->fetchAll();
        return array_map(array($this, 'rowToArticle'), $rows);
    }

    public function allPublished($limit = null)
    {
        $sql = "SELECT * FROM blog_articles WHERE status = 'published' AND published_at IS NOT NULL ORDER BY published_at DESC";
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int)$limit;
        }
        $rows = $this->pdo->query($sql)->fetchAll();
        return array_map(array($this, 'rowToArticle'), $rows);
    }

    public function find($id)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM blog_articles WHERE id = :id');
        $stmt->execute(array(':id' => (int)$id));
        $row = $stmt->fetch();
        return $row ? $this->rowToArticle($row) : null;
    }

    public function findBySlug($slug)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM blog_articles WHERE slug = :slug');
        $stmt->execute(array(':slug' => (string)$slug));
        $row = $stmt->fetch();
        return $row ? $this->rowToArticle($row) : null;
    }

    public function findPublishedBySlug($slug)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM blog_articles WHERE slug = :slug AND status = 'published' AND published_at IS NOT NULL");
        $stmt->execute(array(':slug' => (string)$slug));
        $row = $stmt->fetch();
        return $row ? $this->rowToArticle($row) : null;
    }

    public function slugExists($slug, $excludeId = null)
    {
        if ($excludeId === null) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM blog_articles WHERE slug = :slug');
            $stmt->execute(array(':slug' => (string)$slug));
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM blog_articles WHERE slug = :slug AND id != :id');
            $stmt->execute(array(':slug' => (string)$slug, ':id' => (int)$excludeId));
        }
        return (int)$stmt->fetchColumn() > 0;
    }

    public function create(array $fields)
    {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO blog_articles
            (slug, title, meta_description, canonical_url, status, excerpt, content_json, cover_image_url, author, generated_by_llm, published_at, created_at, updated_at)
            VALUES (:slug, :title, :meta_description, :canonical_url, :status, :excerpt, :content_json, :cover_image_url, :author, :generated_by_llm, :published_at, :created_at, :updated_at)');
        $stmt->execute($this->params($fields, $now, $now));
        return $this->find((int)$this->pdo->lastInsertId());
    }

    public function update($id, array $fields)
    {
        $now = gmdate('c');
        $params = $this->params($fields, $now, $now);
        unset($params[':created_at']);
        $params[':id'] = (int)$id;
        $stmt = $this->pdo->prepare('UPDATE blog_articles SET
            slug = :slug, title = :title, meta_description = :meta_description, canonical_url = :canonical_url,
            status = :status, excerpt = :excerpt, content_json = :content_json, cover_image_url = :cover_image_url,
            author = :author, generated_by_llm = :generated_by_llm, published_at = :published_at, updated_at = :updated_at
            WHERE id = :id');
        $stmt->execute($params);
        return $this->find($id);
    }

    public function delete($id)
    {
        $stmt = $this->pdo->prepare('DELETE FROM blog_articles WHERE id = :id');
        $stmt->execute(array(':id' => (int)$id));
        return $stmt->rowCount() > 0;
    }

    private function params(array $fields, $createdAt, $updatedAt)
    {
        return array(
            ':slug' => isset($fields['slug']) ? (string)$fields['slug'] : '',
            ':title' => isset($fields['title']) ? (string)$fields['title'] : '',
            ':meta_description' => isset($fields['meta_description']) ? (string)$fields['meta_description'] : '',
            ':canonical_url' => isset($fields['canonical_url']) ? (string)$fields['canonical_url'] : '',
            ':status' => isset($fields['status']) && $fields['status'] === 'published' ? 'published' : 'draft',
            ':excerpt' => isset($fields['excerpt']) ? (string)$fields['excerpt'] : '',
            ':content_json' => isset($fields['content_json']) ? (string)$fields['content_json'] : '[]',
            ':cover_image_url' => isset($fields['cover_image_url']) ? (string)$fields['cover_image_url'] : '',
            ':author' => isset($fields['author']) ? (string)$fields['author'] : '',
            ':generated_by_llm' => !empty($fields['generated_by_llm']) ? 1 : 0,
            ':published_at' => isset($fields['published_at']) ? $fields['published_at'] : null,
            ':created_at' => $createdAt,
            ':updated_at' => $updatedAt
        );
    }

    private function rowToArticle($row)
    {
        $row['id'] = (int)$row['id'];
        $row['generated_by_llm'] = !empty($row['generated_by_llm']);
        $blocks = json_decode($row['content_json'], true);
        $row['content_blocks'] = is_array($blocks) ? $blocks : array();
        return $row;
    }
}
