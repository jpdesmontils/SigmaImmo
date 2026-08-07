<?php
require_once __DIR__ . '/../app/API/PropertyAnalysisAPIProcessor.php';

class TestablePropertyAnalysisAPIProcessor extends PropertyAnalysisAPIProcessor
{
    public function __construct() {}

    public function resolvedPhpCliBinary()
    {
        return $this->phpCliBinary();
    }
}

$processor = new TestablePropertyAnalysisAPIProcessor();
$previous = getenv('SIGMAIMMO_PHP_CLI');

putenv('SIGMAIMMO_PHP_CLI=/opt/php/bin/php');
assertPhpCli($processor->resolvedPhpCliBinary() === '/opt/php/bin/php', 'the configured PHP CLI binary is used');

putenv('SIGMAIMMO_PHP_CLI=');
assertPhpCli($processor->resolvedPhpCliBinary() === '/usr/bin/php', 'an empty configuration uses the default PHP CLI binary');

putenv('SIGMAIMMO_PHP_CLI');
assertPhpCli($processor->resolvedPhpCliBinary() === '/usr/bin/php', 'a missing configuration uses the default PHP CLI binary');

if ($previous === false) putenv('SIGMAIMMO_PHP_CLI');
else putenv('SIGMAIMMO_PHP_CLI=' . $previous);

echo "property_analysis_php_cli_test ok\n";

function assertPhpCli($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, 'Assertion failed: ' . $message . "\n");
        exit(1);
    }
}
