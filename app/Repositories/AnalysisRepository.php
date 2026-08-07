<?php
require_once dirname(__DIR__) . '/Database/Connection.php';

class AnalysisRepository
{
    private $pdo;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?: DatabaseConnection::get();
    }

    public function find($propertyId, $type)
    {
        $stmt = $this->pdo->prepare("SELECT result_json FROM analyses WHERE property_id=:property_id AND type=:type AND status='completed'");
        $stmt->execute(array(':property_id' => (string)$propertyId, ':type' => (string)$type));
        $json = $stmt->fetchColumn();
        if ($json === false) return null;
        $value = json_decode((string)$json, true);
        return is_array($value) ? $value : null;
    }

    public function save($propertyId, $type, $analysis, $summary = null, $score = null)
    {
        $resultJson = json_encode($analysis, JSON_UNESCAPED_UNICODE);
        $summaryJson = json_encode($summary, JSON_UNESCAPED_UNICODE);
        if ($resultJson === false || $summaryJson === false) throw new RuntimeException('L’analyse ne peut pas être encodée.');
        $now = gmdate('c');
        $params = array(':property_id'=>(string)$propertyId, ':type'=>(string)$type, ':summary'=>$summaryJson, ':result'=>$resultJson, ':score'=>$score, ':now'=>$now);
        $update = $this->pdo->prepare("UPDATE analyses SET status='completed',summary_json=:summary,result_json=:result,score=:score,updated_at=:now WHERE property_id=:property_id AND type=:type");
        $update->execute($params);
        if ($update->rowCount() > 0) return;
        $insert = $this->pdo->prepare("INSERT INTO analyses (property_id,type,status,summary_json,result_json,score,created_at,updated_at) VALUES (:property_id,:type,'completed',:summary,:result,:score,:now,:now)");
        $insert->execute($params);
    }
}
