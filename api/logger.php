<?php
/** Journalisation JSONL centralisée, sans données secrètes. */
define('LOG_DIR', __DIR__ . '/../storage/log/');

function appLogDirectory($channel)
{
    $safeChannel = preg_replace('/[^a-z0-9_-]/i', '_', (string)$channel);
    return LOG_DIR . ($safeChannel === 'ai' ? 'ai/' : '');
}

function ensureAppLogDirectory($channel = 'app')
{
    $directory = appLogDirectory($channel);
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Impossible de créer le répertoire de logs : ' . $directory);
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('Le répertoire de logs n’est pas accessible en écriture : ' . $directory);
    }
    return $directory;
}

function appLogPath($channel = 'app')
{
    $safeChannel = preg_replace('/[^a-z0-9_-]/i', '_', (string)$channel);
    return ensureAppLogDirectory($safeChannel) . ($safeChannel === 'ai' ? 'analysis.log' : 'app.log');
}

function appLog($channel, $event, array $context = array())
{
    $record = array_merge(array('timestamp' => gmdate('c'), 'event' => $event), $context);
    $encoded = json_encode($record, JSON_UNESCAPED_UNICODE);
    if ($encoded === false || file_put_contents(appLogPath($channel), $encoded . "\n", FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException('Impossible d’écrire le journal applicatif.');
    }
    return true;
}

function aiLog($event, array $context = array())
{
    return appLog('ai', $event, $context);
}
