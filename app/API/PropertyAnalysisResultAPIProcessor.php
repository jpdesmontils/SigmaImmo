<?php
require_once __DIR__ . '/cAPI_Processor.php';
require_once dirname(dirname(__DIR__)) . '/api/analysis_types.php';
require_once dirname(__DIR__) . '/Repositories/PropertyRepository.php';
class PropertyAnalysisResultAPIProcessor extends cAPI_Processor
{
    public function process($method, $body)
    {
        $propertyId = isset($this->params['property_id']) ? (string)$this->params['property_id'] : ''; $type = isset($this->params['type']) ? (string)$this->params['type'] : '';
        if (!validAnalysisType($type)) throw new APIException(422, 'analysis.invalid_type', 'Type d’analyse invalide.');
        $properties = new PropertyRepository($this->pdo);
        $property = $method === 'GET'
            ? $properties->findAccessible($propertyId, $this->user['id'])
            : $properties->find($propertyId, $this->user['id']);
        if (!$property) throw new APIException(404, 'property.not_found', 'Annonce introuvable.');
        $stmt = $this->pdo->prepare('SELECT * FROM analyses WHERE property_id=:id AND type=:type LIMIT 1'); $stmt->execute(array(':id'=>$propertyId, ':type'=>$type)); $row = $stmt->fetch();
        if (!$row) throw new APIException(404, 'analysis.not_found', 'Analyse introuvable.');
        if ($method === 'DELETE') { $delete=$this->pdo->prepare('DELETE FROM analyses WHERE id=:id'); $delete->execute(array(':id'=>$row['id'])); return array('data'=>array('deleted'=>true), 'meta'=>array()); }
        $analysis=json_decode((string)$row['result_json'], true); return array('data'=>array('analysis'=>$analysis, 'listing'=>array('images'=>$property['images'], 'url'=>isset($property['url'])?$property['url']:null)), 'meta'=>array());
    }
}
