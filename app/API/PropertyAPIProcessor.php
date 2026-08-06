<?php
require_once __DIR__ . '/cAPI_Processor.php';
require_once dirname(__DIR__) . '/Repositories/PropertyRepository.php';
require_once dirname(__DIR__) . '/Services/PlanService.php';
class PropertyAPIProcessor extends cAPI_Processor
{
    protected function create($body)
    {
        $repo = new PropertyRepository($this->pdo); $plans = new PlanService();
        if (isset($body['id']) && $repo->find((string)$body['id'])) throw new APIException(409, 'property.id_conflict', 'Un bien utilise déjà cet identifiant.');
        if (!$plans->canAddFavorite($this->user, $repo->countActiveByUser($this->user['id']))) throw new APIException(429, 'property.quota_reached', 'Le quota de favoris est atteint.');
        if (!isset($body['visibility'])) $body['visibility'] = $plans->defaultVisibility($this->user);
        return parent::create($body);
    }
}
