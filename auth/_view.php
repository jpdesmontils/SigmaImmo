<?php
require_once __DIR__ . '/../app/Views/TemplateRenderer.php';
require_once __DIR__ . '/../app/Views/LabelProvider.php';

function auth_labels()
{
    static $labels = null;
    if ($labels === null) {
        $labels = new LabelProvider();
    }
    return $labels;
}

function auth_error_label($code)
{
    return auth_labels()->get('errors.' . $code, $code);
}

function auth_render_template($template, array $context)
{
    $labels = auth_labels();
    $renderer = new TemplateRenderer();
    $context['brand'] = $labels->get('layout.brand');
    $context['page_title'] = isset($context['page_title']) ? $context['page_title'] : $context['brand'];
    header('Content-Type: text/html; charset=utf-8');
    echo $renderer->renderWithLayout($template, 'layouts/auth', $context);
}
