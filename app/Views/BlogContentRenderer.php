<?php
/** Transforme les blocs de contenu JSON d'un article de blog en HTML sûr. */
class BlogContentRenderer
{
    public function render(array $blocks)
    {
        $html = '';
        foreach ($blocks as $block) {
            if (!is_array($block) || !isset($block['type'])) {
                continue;
            }
            switch ($block['type']) {
                case 'heading':
                    $html .= '<h2>' . $this->text($block, 'text') . '</h2>' . "\n";
                    break;
                case 'paragraph':
                    $html .= '<p>' . $this->text($block, 'text') . '</p>' . "\n";
                    break;
                case 'list':
                    $html .= '<ul>' . "\n";
                    foreach ($this->items($block) as $item) {
                        $html .= '<li>' . htmlspecialchars((string)$item, ENT_QUOTES, 'UTF-8') . '</li>' . "\n";
                    }
                    $html .= '</ul>' . "\n";
                    break;
                case 'quote':
                    $html .= '<blockquote>' . $this->text($block, 'text') . '</blockquote>' . "\n";
                    break;
            }
        }
        return $html;
    }

    private function text($block, $key)
    {
        $value = isset($block[$key]) ? (string)$block[$key] : '';
        return nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
    }

    private function items($block)
    {
        return isset($block['items']) && is_array($block['items']) ? $block['items'] : array();
    }
}
