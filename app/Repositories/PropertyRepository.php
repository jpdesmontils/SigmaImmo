<?php
require_once dirname(__DIR__) . '/Database/Connection.php';

class PropertyRepository
{
    private $pdo;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?: DatabaseConnection::get();
    }

    public function allActive($userId = null, $attributeUserId = null)
    {
        if ($userId === null) {
            $rows = $this->pdo->query('SELECT * FROM properties WHERE deleted_at IS NULL ORDER BY COALESCE(captured_at, 0) DESC')->fetchAll();
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM properties WHERE user_id = :user_id AND deleted_at IS NULL ORDER BY COALESCE(captured_at, 0) DESC');
            $stmt->execute(array(':user_id' => (int)$userId));
            $rows = $stmt->fetchAll();
        }
        $items = array_map(array($this, 'rowToListing'), $rows);
        return $attributeUserId === null ? $items : $this->attachUserAttributes($items, $attributeUserId);
    }

    public function allAccessible($userId, $isAdmin = false)
    {
        if ($isAdmin) return $this->allActive(null, $userId);
        $stmt = $this->pdo->prepare("SELECT * FROM properties WHERE deleted_at IS NULL AND (user_id = :user_id OR visibility = 'shared') ORDER BY COALESCE(captured_at, 0) DESC");
        $stmt->execute(array(':user_id' => (int)$userId));
        return $this->attachUserAttributes(array_map(array($this, 'rowToListing'), $stmt->fetchAll()), $userId);
    }

    public function countActiveByUser($userId)
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM properties WHERE user_id = :user_id AND deleted_at IS NULL');
        $stmt->execute(array(':user_id' => (int)$userId));
        return (int)$stmt->fetchColumn();
    }

    public function find($id, $userId = null)
    {
        if ($userId === null) {
            $stmt = $this->pdo->prepare('SELECT * FROM properties WHERE id = :id AND deleted_at IS NULL');
            $stmt->execute(array(':id' => $id));
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM properties WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL');
            $stmt->execute(array(':id' => $id, ':user_id' => (int)$userId));
        }
        $row = $stmt->fetch();
        return $row ? $this->rowToListing($row) : null;
    }

    public function findAccessible($id, $userId, $isAdmin = false)
    {
        if ($isAdmin) return $this->withUserAttributes($this->find($id), $userId);
        $stmt = $this->pdo->prepare("SELECT * FROM properties WHERE id = :id AND deleted_at IS NULL AND (user_id = :user_id OR visibility = 'shared')");
        $stmt->execute(array(':id' => $id, ':user_id' => (int)$userId));
        $row = $stmt->fetch();
        return $row ? $this->withUserAttributes($this->rowToListing($row), $userId) : null;
    }

    public function upsert($listing, $user = null)
    {
        $listing = $this->sanitizeListing($listing);
        if (empty($listing['id'])) {
            return false;
        }
        $userId = is_array($user) && isset($user['id']) ? (int)$user['id'] : (isset($listing['userId']) ? (int)$listing['userId'] : null);
        $existing = $this->find($listing['id']);
        if ($existing) {
            foreach (array('address', 'location', 'price', 'surface', 'rooms', 'terrain') as $field) {
                if (isset($existing[$field]) && $existing[$field] !== '' && $existing[$field] !== null) {
                    $listing[$field] = $existing[$field];
                }
            }
        }
        $now = gmdate('c');
        $columns = array('id', 'user_id', 'visibility', 'source', 'source_url', 'title', 'location', 'address', 'price', 'price_text', 'surface', 'surface_text', 'rooms', 'bedrooms', 'terrain', 'dpe', 'ges', 'description', 'image_url', 'images_json', 'features_json', 'coords_json', 'agency', 'price_reduction', 'photo_count', 'raw_json', 'captured_at', 'scraped_at', 'created_at', 'updated_at', 'deleted_at');
        if ($userId !== null) $listing['userId'] = $userId;
        if (is_array($user)) {
            require_once dirname(__DIR__) . '/Services/PlanService.php';
            $planService = new PlanService();
            if (!$existing && !$planService->canAddFavorite($user, $this->countActiveByUser($user['id']))) {
                throw new RuntimeException('property.quota_reached');
            }
            if (!isset($listing['visibility'])) {
                $listing['visibility'] = $planService->defaultVisibility($user);
            }
        }
        $params = $this->params($listing, $now, $existing ? $existing['createdAt'] : $now);
        if ($existing) {
            $assignments = array();
            foreach ($columns as $column) {
                if ($column === 'id' || $column === 'created_at') continue;
                $assignments[] = $column . ' = :' . $column;
            }
            $sql = 'UPDATE properties SET ' . implode(', ', $assignments) . ' WHERE id = :id';
            unset($params[':created_at']);
        } else {
            $placeholders = array();
            foreach ($columns as $column) $placeholders[] = ':' . $column;
            $sql = 'INSERT INTO properties (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        }
        $stmt = $this->pdo->prepare($sql);
        $saved = $stmt->execute($params);
        if ($saved && $userId !== null) $this->saveUserAttributes($listing['id'], $userId, $listing);
        return $saved;
    }

    public function softDelete($id)
    {
        $stmt = $this->pdo->prepare('UPDATE properties SET deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(array(':id' => $id, ':deleted_at' => gmdate('c'), ':updated_at' => gmdate('c')));
        return $stmt->rowCount() > 0;
    }

    private function sanitizeListing($l)
    {
        if (empty($l['id']) && !empty($l['url'])) $l['id'] = $this->normalizeUrl($l['url']);
        return is_array($l) ? $l : array();
    }

    private function params($l, $now, $createdAt)
    {
        return array(
            ':id' => (string)$l['id'], ':user_id' => isset($l['userId']) && $l['userId'] ? (int)$l['userId'] : null, ':visibility' => isset($l['visibility']) && in_array($l['visibility'], array('shared', 'private'), true) ? $l['visibility'] : 'shared', ':source' => isset($l['source']) ? $l['source'] : 'ga_favorite', ':source_url' => isset($l['url']) ? $l['url'] : '',
            ':title' => isset($l['title']) ? $l['title'] : '', ':location' => isset($l['location']) ? $l['location'] : '', ':address' => isset($l['address']) ? $l['address'] : '',
            ':price' => isset($l['price']) && is_numeric($l['price']) ? (float)$l['price'] : null, ':price_text' => isset($l['priceText']) ? $l['priceText'] : '',
            ':surface' => isset($l['surface']) && is_numeric($l['surface']) ? (float)$l['surface'] : null, ':surface_text' => isset($l['surfaceText']) ? $l['surfaceText'] : '',
            ':rooms' => isset($l['rooms']) ? (string)$l['rooms'] : '', ':bedrooms' => isset($l['bedrooms']) ? (string)$l['bedrooms'] : '', ':terrain' => isset($l['terrain']) && is_numeric($l['terrain']) ? (float)$l['terrain'] : null,
            ':dpe' => isset($l['dpe']) ? $l['dpe'] : '', ':ges' => isset($l['ges']) ? $l['ges'] : '', ':description' => isset($l['description']) ? $l['description'] : '', ':image_url' => isset($l['imageUrl']) ? $l['imageUrl'] : '',
            ':images_json' => $this->json(isset($l['images']) ? $l['images'] : array()), ':features_json' => $this->json(isset($l['features']) ? $l['features'] : array()), ':coords_json' => $this->json(isset($l['coords']) ? $l['coords'] : null),
            ':agency' => isset($l['agency']) ? $l['agency'] : '', ':price_reduction' => isset($l['priceReduction']) ? $l['priceReduction'] : '', ':photo_count' => isset($l['photoCount']) && is_numeric($l['photoCount']) ? (int)$l['photoCount'] : null,
            ':raw_json' => $this->json($this->withoutUserAttributes($l)), ':captured_at' => isset($l['capturedAt']) && is_numeric($l['capturedAt']) ? (int)$l['capturedAt'] : time() * 1000, ':scraped_at' => isset($l['scrapedAt']) && is_numeric($l['scrapedAt']) ? (int)$l['scrapedAt'] : null,
            ':created_at' => $createdAt, ':updated_at' => $now, ':deleted_at' => null
        );
    }

    public function rowToListing($row)
    {
        $raw = json_decode(isset($row['raw_json']) ? $row['raw_json'] : '{}', true);
        $item = is_array($raw) ? $raw : array();
        $item = $this->withoutUserAttributes($item);
        $map = array('id'=>'id','source_url'=>'url','title'=>'title','location'=>'location','address'=>'address','price'=>'price','price_text'=>'priceText','surface'=>'surface','surface_text'=>'surfaceText','rooms'=>'rooms','bedrooms'=>'bedrooms','terrain'=>'terrain','dpe'=>'dpe','ges'=>'ges','description'=>'description','image_url'=>'imageUrl','agency'=>'agency','price_reduction'=>'priceReduction','photo_count'=>'photoCount','captured_at'=>'capturedAt','scraped_at'=>'scrapedAt','source'=>'source');
        foreach ($map as $db => $api) if (array_key_exists($db, $row) && $row[$db] !== null) $item[$api] = $row[$db];
        $item['images'] = $this->decode($row['images_json'], array());
        $item['features'] = $this->decode($row['features_json'], array());
        $item['coords'] = $this->decode($row['coords_json'], null);
        $item['userId'] = isset($row['user_id']) ? $row['user_id'] : null;
        $item['visibility'] = isset($row['visibility']) ? $row['visibility'] : 'shared';
        $item['createdAt'] = $row['created_at'];
        $item['updatedAt'] = $row['updated_at'];
        return $item;
    }

    private function json($value) { return json_encode($value, JSON_UNESCAPED_UNICODE); }
    private function decode($json, $default) { $v = json_decode((string)$json, true); return is_array($v) ? $v : $default; }
    private function normalizeUrl($url) { $parts = parse_url($url); return trim(isset($parts['path']) ? $parts['path'] : $url, '/'); }

    public function saveUserAttributes($propertyId, $userId, $fields)
    {
        $map = array('selection'=>'status', 'notes'=>'notes', 'visitAt'=>'visit_at', 'agentName'=>'agent_name', 'agentPhone'=>'agent_phone', 'agentEmail'=>'agent_email');
        $values = array();
        foreach ($map as $field => $column) if (array_key_exists($field, $fields)) $values[$column] = $fields[$field];
        if (!$values) return true;
        if (!$this->areUserAttributesValid($fields)) return false;
        $now = gmdate('c');
        $insert = $this->pdo->prepare('INSERT OR IGNORE INTO property_user_attributes (property_id,user_id,created_at,updated_at) VALUES (:property_id,:user_id,:created_at,:updated_at)');
        $insert->execute(array(':property_id'=>(string)$propertyId, ':user_id'=>(int)$userId, ':created_at'=>$now, ':updated_at'=>$now));
        $sets = array(); $params = array(':property_id'=>(string)$propertyId, ':user_id'=>(int)$userId, ':updated_at'=>$now);
        foreach ($values as $column => $value) { $sets[] = $column . '=:' . $column; $params[':' . $column] = $value; }
        $sets[] = 'updated_at=:updated_at';
        $stmt = $this->pdo->prepare('UPDATE property_user_attributes SET ' . implode(',', $sets) . ' WHERE property_id=:property_id AND user_id=:user_id');
        return $stmt->execute($params);
    }

    public function areUserAttributesValid($fields)
    {
        return !isset($fields['selection']) || $fields['selection'] === null || in_array($fields['selection'], array('shortlist', 'ecartee', 'a_visiter', 'visite'), true);
    }

    private function attachUserAttributes($items, $userId)
    {
        if (!$items) return $items;
        $byId = array(); foreach ($items as $index => $item) $byId[(string)$item['id']] = $index;
        $placeholders = implode(',', array_fill(0, count($byId), '?'));
        $stmt = $this->pdo->prepare('SELECT * FROM property_user_attributes WHERE user_id=? AND property_id IN (' . $placeholders . ')');
        $stmt->execute(array_merge(array((int)$userId), array_keys($byId)));
        foreach ($stmt->fetchAll() as $row) $items[$byId[(string)$row['property_id']]] = $this->applyUserAttributeRow($items[$byId[(string)$row['property_id']]], $row);
        return $items;
    }

    private function withUserAttributes($item, $userId)
    {
        if (!$item) return null;
        $stmt = $this->pdo->prepare('SELECT * FROM property_user_attributes WHERE property_id=:property_id AND user_id=:user_id');
        $stmt->execute(array(':property_id'=>(string)$item['id'], ':user_id'=>(int)$userId));
        $row = $stmt->fetch();
        return $row ? $this->applyUserAttributeRow($item, $row) : $item;
    }

    private function applyUserAttributeRow($item, $row)
    {
        $map = array('status'=>'selection', 'notes'=>'notes', 'visit_at'=>'visitAt', 'agent_name'=>'agentName', 'agent_phone'=>'agentPhone', 'agent_email'=>'agentEmail');
        foreach ($map as $column => $field) $item[$field] = $row[$column];
        return $item;
    }

    private function withoutUserAttributes($listing)
    {
        foreach (array('selection','notes','visitAt','visit_at','agentName','agent_name','agentPhone','agent_phone','agentEmail','agent_email') as $field) unset($listing[$field]);
        return $listing;
    }
}
