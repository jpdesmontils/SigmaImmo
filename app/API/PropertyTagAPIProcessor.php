<?php
require_once __DIR__ . '/cAPI_Processor.php';
class PropertyTagAPIProcessor extends cAPI_Processor
{
    public function process($method, $body)
    {
        if (!isset($this->params['property_id'])) throw new APIException(400, 'property.id_required', 'Identifiant du bien requis.');
        if (!is_array($body) || !isset($body['tag']) || trim((string)$body['tag']) === '') throw new APIException(422, 'tag.required', 'Le tag est obligatoire.');
        $propertyId = (string)$this->params['property_id']; $this->assertOwnedProperty($propertyId);
        $tag = mb_substr(trim(strip_tags((string)$body['tag'])), 0, 80);
        $stmt = $this->pdo->prepare('INSERT OR IGNORE INTO property_tags (property_id, tag, created_at) VALUES (:property_id, :tag, :created_at)');
        $stmt->execute(array(':property_id' => $propertyId, ':tag' => $tag, ':created_at' => gmdate('c')));
        $find = $this->pdo->prepare('SELECT * FROM property_tags WHERE property_id = :property_id AND tag = :tag'); $find->execute(array(':property_id' => $propertyId, ':tag' => $tag));
        return array('data' => $find->fetch(), 'meta' => array('created' => $stmt->rowCount() > 0));
    }
    private function assertOwnedProperty($id)
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM properties WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'); $stmt->execute(array(':id' => $id, ':user_id' => (int)$this->user['id']));
        if (!$stmt->fetchColumn()) throw new APIException(403, 'property.forbidden', 'Modification du bien interdite.');
    }
}
