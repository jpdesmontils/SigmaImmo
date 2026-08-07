<?php
require_once __DIR__ . '/cAPI_Processor.php';
require_once dirname(dirname(__DIR__)) . '/api/analysis_types.php';
require_once dirname(dirname(__DIR__)) . '/api/logger.php';
class PropertyAnalysisAPIProcessor extends cAPI_Processor
{
    public function process($method, $body)
    {
        $propertyId = isset($this->params['property_id']) ? (string)$this->params['property_id'] : '';
        $type = is_array($body) && isset($body['type']) ? (string)$body['type'] : '';
        if (!validAnalysisType($type)) throw new APIException(422, 'analysis.invalid_type', 'Type d’analyse invalide.');
        $this->assertPropertyCalculationAllowed($propertyId);
        $active = $this->pdo->prepare("SELECT id FROM analysis_jobs WHERE property_id = :id AND status IN ('queued','running') LIMIT 1"); $active->execute(array(':id' => $propertyId));
        if ($active->fetchColumn()) throw new APIException(409, 'analysis.already_running', 'Une analyse est déjà en cours.');
        $phpBinary = $this->phpCliBinary();
        if (!function_exists('exec') || !is_executable($phpBinary)) throw new APIException(503, 'analysis.worker_unavailable', 'Le worker d’analyse est indisponible.');
        $now = gmdate('c'); $stmt = $this->pdo->prepare('INSERT INTO analysis_jobs (property_id,type,status,queued_at,created_at,updated_at) VALUES (:property_id,:type,\'queued\',:now,:now,:now)'); $stmt->execute(array(':property_id' => $propertyId, ':type' => $type, ':now' => $now));
        $jobId = (int)$this->pdo->lastInsertId();
        $log = ensureAppLogDirectory() . 'analysis-worker.log';
        $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg(dirname(dirname(__DIR__)) . '/api/analyze_worker.php') . ' ' . escapeshellarg($propertyId) . ' ' . escapeshellarg($type) . ' >> ' . escapeshellarg($log) . ' 2>&1 &';
        @exec($command);
        return array('data' => array('id' => $jobId, 'property_id' => $propertyId, 'type' => $type, 'status' => 'queued'), 'meta' => array());
    }

    protected function phpCliBinary()
    {
        $configured = getenv('SIGMAIMMO_PHP_CLI');
        return $configured !== false && trim($configured) !== '' ? trim($configured) : '/usr/bin/php';
    }
}
