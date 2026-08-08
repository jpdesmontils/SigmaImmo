<?php
require_once __DIR__ . '/../api/analysis_types.php';

$previous = getenv('TTL_LLM_IN_SEC');

putenv('TTL_LLM_IN_SEC');
assertTtl(llmJobTtlSeconds() === 1200, 'a missing TTL uses 1200 seconds');

putenv('TTL_LLM_IN_SEC=');
assertTtl(llmJobTtlSeconds() === 1200, 'an empty TTL uses 1200 seconds');

putenv('TTL_LLM_IN_SEC=42');
assertTtl(llmJobTtlSeconds() === 42, 'a configured positive TTL is used');
assertTtl(llmJobTimeoutMessage(42) === 'L’analyse a dépassé sa durée maximale d’exécution de 42 secondes. Veuillez réessayer.', 'the timeout message is explicit');

putenv('TTL_LLM_IN_SEC=invalid');
$invalidRejected = false;
try { llmJobTtlSeconds(); } catch (RuntimeException $error) { $invalidRejected = strpos($error->getMessage(), 'TTL_LLM_IN_SEC') !== false; }
assertTtl($invalidRejected, 'an invalid TTL is rejected explicitly');

if ($previous === false) putenv('TTL_LLM_IN_SEC');
else putenv('TTL_LLM_IN_SEC=' . $previous);

echo "analysis_job_ttl_test ok\n";

function assertTtl($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, 'Assertion failed: ' . $message . "\n");
        exit(1);
    }
}
