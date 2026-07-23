<?php
/** Journalisation JSONL centralisée, sans données secrètes. */
define('LOG_DIR', __DIR__ . '/../log/');
function appLog($channel, $event, array $context = []) {
    $safeChannel = preg_replace('/[^a-z0-9_-]/i', '_', $channel);
    $directory = LOG_DIR . ($safeChannel === 'ai' ? 'ai/' : '');
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) return false;
    $record = array_merge(['timestamp' => gmdate('c'), 'event' => $event], $context);
    return @file_put_contents($directory . ($safeChannel === 'ai' ? 'analysis.log' : 'app.log'), json_encode($record, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX) !== false;
}
function aiLog($event, array $context = []) { return appLog('ai', $event, $context); }
