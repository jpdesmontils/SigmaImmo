<?php
require_once __DIR__ . '/cAPI_Processor.php';
require_once dirname(__DIR__) . '/Repositories/PropertyRepository.php';
require_once dirname(__DIR__) . '/Services/AuditLogger.php';

class SyncAPIProcessor extends cAPI_Processor
{
    public function process($method, $body)
    {
        if (!is_array($body) || !isset($body['favorites']) || !is_array($body['favorites'])) throw new APIException(400, 'sync.invalid_favorites', 'Le champ favorites doit être un tableau.');
        $repo = new PropertyRepository($this->pdo); $audit = new AuditLogger($this->pdo); $added = 0; $updated = 0;
        foreach ($body['favorites'] as $listing) {
            if (!is_array($listing) || empty($listing['id'])) continue;
            $existing = $repo->find((string)$listing['id'], $this->user['id']);
            $global = $repo->find((string)$listing['id']);
            if ($global && !$existing) throw new APIException(409, 'property.id_conflict', 'Ce bien appartient déjà à un autre utilisateur.');
            try { $repo->upsert($listing, $this->user); }
            catch (RuntimeException $e) { if ($e->getMessage() === 'property.quota_reached') throw new APIException(429, 'property.quota_reached', 'Le quota de favoris est atteint.'); throw $e; }
            $existing ? $updated++ : $added++;
            $audit->log($existing ? 'property.updated' : 'property.created', $this->user['id'], (string)$listing['id'], array('source' => 'api.v1.sync'));
        }
        return array('data' => array('favorites_added' => $added, 'favorites_updated' => $updated), 'meta' => array());
    }
}
