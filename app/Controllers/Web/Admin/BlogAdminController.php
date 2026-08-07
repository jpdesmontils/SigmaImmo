<?php
require_once dirname(dirname(dirname(__DIR__))) . '/Views/TemplateRenderer.php';
require_once dirname(dirname(dirname(__DIR__))) . '/Services/BlogArticleService.php';

/** Panneau admin de gestion des articles du blog (réservé aux comptes plan=admin). */
class BlogAdminController
{
    private $renderer;
    private $articles;

    private static $flashLabels = array(
        'saved' => 'Article enregistré.',
        'published' => 'Article publié.',
        'unpublished' => 'Article dépublié.',
        'deleted' => 'Article supprimé.',
        'generated' => 'Brouillon généré par IA. Relisez-le avant publication.'
    );

    public function __construct(TemplateRenderer $renderer = null, BlogArticleService $articles = null)
    {
        $this->renderer = $renderer ?: new TemplateRenderer();
        $this->articles = $articles ?: new BlogArticleService();
    }

    public function index($flashCode = null)
    {
        $items = array_map(function ($a) {
            return array(
                'id' => $a['id'],
                'title' => $a['title'],
                'slug' => $a['slug'],
                'status' => $a['status'],
                'is_published' => $a['status'] === 'published',
                'updated_at' => str_replace('T', ' ', substr((string)$a['updated_at'], 0, 16))
            );
        }, $this->articles->listForAdmin());

        $this->render('admin/blog/index', array(
            'page_title' => 'Articles du blog',
            'articles' => $items,
            'flash' => $flashCode && isset(self::$flashLabels[$flashCode]) ? self::$flashLabels[$flashCode] : null
        ));
    }

    public function form($id = null, $error = null, $input = null)
    {
        $article = $id ? $this->articles->find($id) : null;
        if ($id && !$article) {
            http_response_code(404);
            echo 'Article introuvable.';
            return;
        }

        $data = $input;
        if ($data === null) {
            $data = $article ? array(
                'id' => $article['id'],
                'title' => $article['title'],
                'slug' => $article['slug'],
                'meta_description' => $article['meta_description'],
                'canonical_url' => $article['canonical_url'],
                'excerpt' => $article['excerpt'],
                'content_blocks_json' => json_encode($article['content_blocks'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                'cover_image_url' => $article['cover_image_url'],
                'author' => $article['author'],
                'is_published' => $article['status'] === 'published'
            ) : array(
                'id' => null,
                'title' => '',
                'slug' => '',
                'meta_description' => '',
                'canonical_url' => '',
                'excerpt' => '',
                'content_blocks_json' => '[]',
                'cover_image_url' => '',
                'author' => '',
                'is_published' => false
            );
        }

        $this->render('admin/blog/form', array(
            'page_title' => $article ? "Modifier l'article" : 'Nouvel article',
            'has_error' => $error !== null,
            'error' => $error,
            'form' => $data
        ));
    }

    public function save($id, array $post, $userId)
    {
        $blocksJson = isset($post['content_blocks_json']) ? $post['content_blocks_json'] : '[]';
        $blocks = json_decode($blocksJson, true);
        if (!is_array($blocks)) {
            $this->form($id, 'Le contenu JSON des blocs est invalide.', $this->inputFromPost($id, $post));
            return;
        }

        $input = array(
            'title' => isset($post['title']) ? $post['title'] : '',
            'slug' => isset($post['slug']) ? $post['slug'] : '',
            'meta_description' => isset($post['meta_description']) ? $post['meta_description'] : '',
            'canonical_url' => isset($post['canonical_url']) ? $post['canonical_url'] : '',
            'excerpt' => isset($post['excerpt']) ? $post['excerpt'] : '',
            'content_blocks' => $blocks,
            'cover_image_url' => isset($post['cover_image_url']) ? $post['cover_image_url'] : '',
            'author' => isset($post['author']) ? $post['author'] : '',
            'status' => !empty($post['is_published']) ? 'published' : 'draft'
        );

        try {
            $article = $this->articles->save($input, $id, $userId);
        } catch (InvalidArgumentException $e) {
            $this->form($id, $this->errorLabel($e->getMessage()), $this->inputFromPost($id, $post));
            return;
        }

        header('Location: /admin/blog/edit.php?id=' . $article['id'] . '&flash=saved');
        exit;
    }

    public function setStatus($id, $status, $userId)
    {
        try {
            $this->articles->setStatus($id, $status, $userId);
        } catch (InvalidArgumentException $e) {
            // Article introuvable : ignoré, l'utilisateur revient sur une liste à jour.
        }
        header('Location: /admin/blog/index.php?flash=' . ($status === 'published' ? 'published' : 'unpublished'));
        exit;
    }

    public function delete($id, $userId)
    {
        $this->articles->delete($id, $userId);
        header('Location: /admin/blog/index.php?flash=deleted');
        exit;
    }

    public function generateForm($error = null)
    {
        $this->render('admin/blog/generate', array(
            'page_title' => 'Générer un article (IA)',
            'has_error' => $error !== null,
            'error' => $error
        ));
    }

    public function generate(array $post, $userId)
    {
        $topic = isset($post['topic']) ? trim((string)$post['topic']) : '';
        if ($topic === '') {
            $this->generateForm('Merci de préciser un sujet.');
            return;
        }

        require_once dirname(dirname(dirname(__DIR__))) . '/Services/BlogGeneratorService.php';
        try {
            $generator = new BlogGeneratorService();
            $article = $generator->generateDraft($topic, $userId);
        } catch (Exception $e) {
            $this->generateForm('Génération impossible : ' . $e->getMessage());
            return;
        }

        header('Location: /admin/blog/edit.php?id=' . $article['id'] . '&flash=generated');
        exit;
    }

    private function render($template, array $context)
    {
        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderer->renderWithLayout($template, 'layouts/admin', $context);
    }

    private function errorLabel($code)
    {
        $labels = array(
            'blog.title_required' => 'Le titre est obligatoire.',
            'blog.slug_required' => 'Le slug est obligatoire.',
            'blog.slug_already_exists' => 'Ce slug est déjà utilisé par un autre article.'
        );
        return isset($labels[$code]) ? $labels[$code] : $code;
    }

    private function inputFromPost($id, array $post)
    {
        return array(
            'id' => $id,
            'title' => isset($post['title']) ? $post['title'] : '',
            'slug' => isset($post['slug']) ? $post['slug'] : '',
            'meta_description' => isset($post['meta_description']) ? $post['meta_description'] : '',
            'canonical_url' => isset($post['canonical_url']) ? $post['canonical_url'] : '',
            'excerpt' => isset($post['excerpt']) ? $post['excerpt'] : '',
            'content_blocks_json' => isset($post['content_blocks_json']) ? $post['content_blocks_json'] : '[]',
            'cover_image_url' => isset($post['cover_image_url']) ? $post['cover_image_url'] : '',
            'author' => isset($post['author']) ? $post['author'] : '',
            'is_published' => !empty($post['is_published'])
        );
    }
}
