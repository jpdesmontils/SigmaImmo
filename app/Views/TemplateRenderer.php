<?php
class TemplateRenderer
{
    private $templatesDir;

    public function __construct($templatesDir = null)
    {
        $this->templatesDir = $templatesDir ?: dirname(dirname(__DIR__)) . '/templates';
    }

    public function render($template, array $context = array())
    {
        return $this->renderString($this->loadTemplate($template), $context);
    }

    public function renderWithLayout($template, $layout, array $context = array())
    {
        $content = $this->render($template, $context);
        $context['content'] = $content;
        return $this->render($layout, $context);
    }

    public function renderString($template, array $context = array())
    {
        $template = $this->renderSections($template, $context);
        $template = $this->renderUnescapedVariables($template, $context);
        return $this->renderEscapedVariables($template, $context);
    }

    private function loadTemplate($template)
    {
        $path = rtrim($this->templatesDir, '/') . '/' . ltrim($template, '/');
        if (substr($path, -9) !== '.mustache') {
            $path .= '.mustache';
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('template.not_found');
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('template.not_readable');
        }
        return $content;
    }

    private function renderSections($template, array $context)
    {
        $self = $this;
        $template = preg_replace_callback('/{{#\s*([A-Za-z0-9_.-]+)\s*}}(.*?){{\/\s*\1\s*}}/s', function($matches) use ($context, $self) {
            $value = $self->value($context, $matches[1]);
            if (is_array($value)) {
                if ($self->isList($value)) {
                    $rendered = '';
                    foreach ($value as $item) {
                        $itemContext = is_array($item) ? array_merge($context, $item) : array_merge($context, array('.' => $item));
                        $rendered .= $self->renderString($matches[2], $itemContext);
                    }
                    return $rendered;
                }
                return count($value) > 0 ? $self->renderString($matches[2], array_merge($context, $value)) : '';
            }
            return $value ? $self->renderString($matches[2], $context) : '';
        }, $template);

        return preg_replace_callback('/{{\^\s*([A-Za-z0-9_.-]+)\s*}}(.*?){{\/\s*\1\s*}}/s', function($matches) use ($context, $self) {
            $value = $self->value($context, $matches[1]);
            $empty = !$value || (is_array($value) && count($value) === 0);
            return $empty ? $self->renderString($matches[2], $context) : '';
        }, $template);
    }

    private function renderUnescapedVariables($template, array $context)
    {
        $self = $this;
        return preg_replace_callback('/{{{\s*([A-Za-z0-9_.-]+)\s*}}}/', function($matches) use ($context, $self) {
            return (string)$self->value($context, $matches[1]);
        }, $template);
    }

    private function renderEscapedVariables($template, array $context)
    {
        $self = $this;
        return preg_replace_callback('/{{\s*([A-Za-z0-9_.-]+)\s*}}/', function($matches) use ($context, $self) {
            return htmlspecialchars((string)$self->value($context, $matches[1]), ENT_QUOTES, 'UTF-8');
        }, $template);
    }

    private function value(array $context, $key)
    {
        if ($key === '.') {
            return isset($context['.']) ? $context['.'] : '';
        }
        $parts = explode('.', $key);
        $value = $context;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return '';
            }
            $value = $value[$part];
        }
        return $value;
    }

    private function isList(array $value)
    {
        return count($value) > 0 && array_keys($value) === range(0, count($value) - 1);
    }
}
