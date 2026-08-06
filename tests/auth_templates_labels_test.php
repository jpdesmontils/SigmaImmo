<?php
require_once __DIR__ . '/../app/Views/LabelProvider.php';
require_once __DIR__ . '/../app/Views/TemplateRenderer.php';

$labelsPath = __DIR__ . '/../resources/labels/fr.json';
$templates = array(
    'layouts/auth',
    'app/auth/login',
    'app/auth/register',
    'app/auth/account'
);
$requiredLabelKeys = array(
    'layout.brand',
    'auth.login.title', 'auth.login.email', 'auth.login.password', 'auth.login.submit', 'auth.login.register_prompt', 'auth.login.register_cta',
    'auth.register.title', 'auth.register.name', 'auth.register.email', 'auth.register.password', 'auth.register.submit', 'auth.register.login_prompt', 'auth.register.login_cta',
    'auth.account.title', 'auth.account.connected', 'auth.account.email', 'auth.account.plan', 'auth.account.favorites', 'auth.account.unlimited', 'auth.account.logout',
    'errors.auth.invalid_email', 'errors.auth.password_too_short', 'errors.auth.email_already_exists', 'errors.auth.invalid_credentials', 'errors.property.quota_reached'
);

assertTrue(is_file($labelsPath), 'labels file exists');
$labels = new LabelProvider($labelsPath);
foreach ($requiredLabelKeys as $key) {
    assertTrue($labels->get($key, null) !== null, 'missing label key ' . $key);
}

$renderer = new TemplateRenderer(__DIR__ . '/../app/Views');
$loginHtml = $renderer->renderWithLayout('app/auth/login', 'layouts/auth', array(
    'brand' => $labels->get('layout.brand'),
    'page_title' => $labels->get('auth.login.title'),
    'labels' => $labels->section('auth.login'),
    'has_error' => true,
    'error' => $labels->get('errors.auth.invalid_credentials'),
    'email' => 'user@example.test'
));
assertContains($loginHtml, 'Connexion', 'login title rendered');
assertContains($loginHtml, 'Identifiants invalides.', 'translated error rendered');
assertContains($loginHtml, 'user@example.test', 'escaped email value rendered');
assertTrue(strpos($loginHtml, '{{') === false, 'all login placeholders are rendered');

$registerHtml = $renderer->render('app/auth/register', array(
    'labels' => $labels->section('auth.register'),
    'has_error' => false,
    'error' => '',
    'name' => '',
    'email' => ''
));
assertContains($registerHtml, 'Créer mon compte', 'register submit label rendered');
assertTrue(strpos($registerHtml, '{{') === false, 'all register placeholders are rendered');

$accountHtml = $renderer->render('app/auth/account', array(
    'labels' => $labels->section('auth.account'),
    'email' => 'paid@example.test',
    'plan' => 'paid',
    'favorites_count' => 11,
    'favorites_limit' => ' ' . $labels->get('auth.account.unlimited')
));
assertContains($accountHtml, 'Favoris', 'account favorites label rendered');
assertContains($accountHtml, '11 illimités', 'account quota rendered');
assertTrue(strpos($accountHtml, '{{') === false, 'all account placeholders are rendered');

foreach ($templates as $template) {
    $path = __DIR__ . '/../app/Views/' . $template . '.mustache';
    assertTrue(is_file($path), 'template exists ' . $template);
}

echo "auth_templates_labels_test ok\n";

function assertTrue($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: " . $message . "\n");
        exit(1);
    }
}

function assertContains($haystack, $needle, $message)
{
    assertTrue(strpos($haystack, $needle) !== false, $message);
}
