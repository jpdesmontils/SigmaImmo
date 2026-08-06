<?php
require_once __DIR__ . '/cAPI_Processor.php';
require_once dirname(__DIR__) . '/Repositories/PropertyRepository.php';
require_once dirname(__DIR__) . '/Services/PlanService.php';
require_once dirname(dirname(__DIR__)) . '/api/analysis_types.php';
class PropertyAPIProcessor extends cAPI_Processor
{
    public function process($method, $body)
    {
        if ($method === 'GET') return isset($this->params['id']) ? $this->detail((string)$this->params['id']) : $this->collection();
        if ($method === 'PATCH') { $body = $this->databaseFields($body); parent::process($method, $body); return $this->detail((string)$this->params['id']); }
        return parent::process($method, $body);
    }

    private function collection()
    {
        $repo = new PropertyRepository($this->pdo); $items = $repo->allActive($this->user['id']);
        foreach ($items as &$item) $this->addAnalysisState($item); unset($item);
        return array('data' => $items, 'meta' => array('count' => count($items)));
    }

    private function detail($id)
    {
        $listing = (new PropertyRepository($this->pdo))->find($id, $this->user['id']);
        if (!$listing) throw new APIException(404, 'resource.not_found', 'Annonce introuvable.');
        $settings = $this->settings();
        if (empty($listing['primaryResidenceCity'])) $listing['primaryResidenceCity'] = $settings['primaryResidenceCity'];
        $analyses = $this->analysisSummaries($id); $job = $this->latestJob($id);
        return array('data' => array('listing' => $listing, 'settings' => $settings, 'analyses' => $analyses, 'job' => $job, 'requirements' => $this->requirements($listing)), 'meta' => array());
    }

    private function addAnalysisState(&$item)
    {
        $item['analyses'] = $this->analysisSummaries((string)$item['id']);
        $item['latestAnalysis'] = latestAnalysisSummary($item['analyses']);
        $job = $this->latestJob((string)$item['id']);
        $item['analysisStatus'] = $job && in_array($job['status'], array('queued', 'running'), true) ? $job['status'] : null;
    }

    private function analysisSummaries($id)
    {
        $stmt = $this->pdo->prepare("SELECT type,summary_json,result_json,score,updated_at FROM analyses WHERE property_id=:id AND status='completed'"); $stmt->execute(array(':id' => $id));
        $result = array_fill_keys(analysisTypes(), false);
        foreach ($stmt->fetchAll() as $row) { $summary = json_decode((string)$row['summary_json'], true); if (!is_array($summary)) $summary = array(); $summary['available'] = true; $summary['analyzedAt'] = $row['updated_at']; $summary['score'] = $row['score']; $result[$row['type']] = $summary; }
        return $result;
    }

    private function latestJob($id)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM analysis_jobs WHERE property_id=:id ORDER BY id DESC LIMIT 1'); $stmt->execute(array(':id' => $id)); return $stmt->fetch() ?: null;
    }

    private function requirements($listing)
    {
        $definitions = array('price'=>array('label'=>'Prix du bien','type'=>'number','suffix'=>'€'),'surface'=>array('label'=>'Surface habitable','type'=>'number','suffix'=>'m²'),'location'=>array('label'=>'Commune ou localisation','type'=>'text'),'dpe'=>array('label'=>'DPE','type'=>'text','pattern'=>'[A-Ga-g]','maxlength'=>'1'),'ges'=>array('label'=>'GES','type'=>'text','pattern'=>'[A-Ga-g]','maxlength'=>'1'),'primaryResidenceCity'=>array('label'=>'Ville de résidence principale','type'=>'text'));
        $result = array(); foreach (analysisTypes() as $type) { $missing = array(); foreach ($definitions as $field=>$definition) if (!isset($listing[$field]) || $listing[$field] === '') $missing[] = array('field'=>$field) + $definition; $result[$type] = array('missing'=>$missing); } return $result;
    }

    private function settings()
    {
        return array('primaryResidenceCity' => !empty($this->user['primary_residence_city']) ? $this->user['primary_residence_city'] : 'Paris');
    }

    private function databaseFields($body)
    {
        if (!is_array($body)) return $body; $map = array('visitAt'=>'visit_at','agentName'=>'agent_name','agentPhone'=>'agent_phone','agentEmail'=>'agent_email');
        foreach ($map as $web=>$column) if (array_key_exists($web, $body)) { $body[$column] = $body[$web]; unset($body[$web]); }
        if (array_key_exists('primaryResidenceCity', $body)) { $raw = (new PropertyRepository($this->pdo))->find((string)$this->params['id'], $this->user['id']); if (!is_array($raw)) $raw = array(); $raw['primaryResidenceCity'] = mb_substr(trim(strip_tags((string)$body['primaryResidenceCity'])), 0, 120); $body['raw_json'] = $raw; unset($body['primaryResidenceCity']); }
        return $body;
    }
    protected function create($body)
    {
        $repo = new PropertyRepository($this->pdo); $plans = new PlanService();
        if (isset($body['id']) && $repo->find((string)$body['id'])) throw new APIException(409, 'property.id_conflict', 'Un bien utilise déjà cet identifiant.');
        if (!$plans->canAddFavorite($this->user, $repo->countActiveByUser($this->user['id']))) throw new APIException(429, 'property.quota_reached', 'Le quota de favoris est atteint.');
        if (!isset($body['visibility'])) $body['visibility'] = $plans->defaultVisibility($this->user);
        return parent::create($body);
    }
}
