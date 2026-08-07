<?php
require_once __DIR__ . '/BlogArticleService.php';

/** Génère un brouillon d'article de blog via un LLM (OpenAI Responses API). */
class BlogGeneratorService
{
    private $blog;

    public function __construct(BlogArticleService $blog = null)
    {
        $this->blog = $blog ?: new BlogArticleService();
    }

    public function generateDraft($topic, $userId = null)
    {
        $this->loadEnv();
        $apiKey = getenv('OPENAI_API_KEY');
        if (!$apiKey) {
            throw new RuntimeException('OPENAI_API_KEY est absente de api/.env.');
        }
        $model = getenv('OPENAI_MODEL') ?: 'gpt-5.3-chat-latest';

        $promptPath = dirname(dirname(__DIR__)) . '/resources/prompts/blog_article.txt';
        $template = @file_get_contents($promptPath);
        if (!$template) {
            throw new RuntimeException('Prompt de génération d’article introuvable.');
        }
        $prompt = str_replace('{{sujet}}', $topic, $template);

        $raw = $this->requestOpenAi($apiKey, $model, $prompt);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded['title']) || empty($decoded['blocks']) || !is_array($decoded['blocks'])) {
            throw new RuntimeException("La réponse IA ne respecte pas le format attendu (title, meta_description, excerpt, blocks).");
        }

        $input = array(
            'title' => (string)$decoded['title'],
            'slug' => isset($decoded['slug']) ? (string)$decoded['slug'] : '',
            'meta_description' => isset($decoded['meta_description']) ? (string)$decoded['meta_description'] : '',
            'excerpt' => isset($decoded['excerpt']) ? (string)$decoded['excerpt'] : '',
            'content_blocks' => $decoded['blocks'],
            'author' => 'BienAuFait',
            'status' => 'draft',
            'generated_by_llm' => true
        );

        try {
            return $this->blog->save($input, null, $userId);
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() !== 'blog.slug_already_exists') {
                throw $e;
            }
            $input['slug'] = $this->blog->slugify($input['title']) . '-' . substr(uniqid(), -5);
            return $this->blog->save($input, null, $userId);
        }
    }

    private function loadEnv()
    {
        $path = dirname(dirname(__DIR__)) . '/api/.env';
        if (!is_file($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $line = preg_replace('/^export\s+/', '', $line);
            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $m)) {
                continue;
            }
            $value = trim($m[2]);
            if (strlen($value) > 1 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }
            if (getenv($m[1]) === false) {
                putenv($m[1] . '=' . $value);
            }
        }
    }

    private function requestOpenAi($apiKey, $model, $prompt)
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extension PHP cURL indisponible.');
        }
        $payload = array(
            'model' => $model,
            'input' => array(array('role' => 'user', 'content' => array(array('type' => 'input_text', 'text' => $prompt)))),
            'text' => array('format' => array('type' => 'json_object'))
        );
        $curl = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($curl, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'),
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE)
        ));
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($body === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('Erreur OpenAI (' . $status . ') : ' . ($error !== '' ? $error : 'réponse refusée sans détail exploitable'));
        }
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $outputs = isset($decoded['output']) ? $decoded['output'] : array();
            foreach ($outputs as $output) {
                $contents = isset($output['content']) ? $output['content'] : array();
                foreach ($contents as $content) {
                    if (isset($content['type']) && $content['type'] === 'output_text') {
                        return $content['text'];
                    }
                }
            }
        }
        throw new RuntimeException('Réponse OpenAI sans contenu texte.');
    }
}
