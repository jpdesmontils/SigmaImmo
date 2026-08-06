<?php
require_once __DIR__ . '/APIException.php';

class cAPI_Processor
{
    protected $pdo;
    protected $route;
    protected $user;
    protected $params;
    private static $allowedTables = array('properties', 'property_tags', 'analyses', 'analysis_jobs');

    public function __construct(PDO $pdo, array $route, array $user, array $params = array())
    {
        $this->pdo = $pdo; $this->route = $route; $this->user = $user; $this->params = $params;
        if (empty($route['table']) || !in_array($route['table'], self::$allowedTables, true)) throw new APIException(500, 'route.invalid', 'Configuration de route invalide.');
    }

    public function process($method, $body)
    {
        if ($method === 'GET') return isset($this->params['id']) ? $this->read($this->params['id']) : $this->listRows();
        if ($method === 'POST') return $this->create($body);
        if ($method === 'PUT' || $method === 'PATCH') return $this->update($this->requiredId(), $body, $method === 'PUT');
        if ($method === 'DELETE') return $this->delete($this->requiredId());
        throw new APIException(405, 'method.not_allowed', 'Méthode non autorisée.');
    }

    protected function listRows()
    {
        list($where, $bindings) = $this->scope();
        $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 25;
        $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
        $sql = 'SELECT * FROM ' . $this->route['table'] . $where . ' ORDER BY ' . $this->primaryKey() . ' DESC LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        foreach ($bindings as $key => $value) $stmt->bindValue($key, $value);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); $stmt->bindValue(':offset', $offset, PDO::PARAM_INT); $stmt->execute();
        return array('data' => array_map(array($this, 'transformRow'), $stmt->fetchAll()), 'meta' => array('limit' => $limit, 'offset' => $offset));
    }

    protected function read($id)
    {
        list($where, $bindings) = $this->scope();
        $where .= ($where === '' ? ' WHERE ' : ' AND ') . $this->primaryKey() . ' = :pk';
        $bindings[':pk'] = $id;
        $stmt = $this->pdo->prepare('SELECT * FROM ' . $this->route['table'] . $where . ' LIMIT 1'); $stmt->execute($bindings); $row = $stmt->fetch();
        if (!$row) throw new APIException(404, 'resource.not_found', 'Ressource introuvable.');
        return array('data' => $this->transformRow($row), 'meta' => array());
    }

    protected function create($body)
    {
        $data = $this->writableData($body, true); $this->applyOwnership($data); $this->applyTimestamps($data, true);
        if (!$data) throw new APIException(422, 'payload.empty', 'Aucune donnée inscriptible.');
        $columns = array_keys($data); $names = array(); foreach ($columns as $column) $names[] = ':' . $column;
        $stmt = $this->pdo->prepare('INSERT INTO ' . $this->route['table'] . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $names) . ')');
        $bindings = array(); foreach ($data as $column => $value) $bindings[':' . $column] = $value; $stmt->execute($bindings);
        $id = isset($data[$this->primaryKey()]) ? $data[$this->primaryKey()] : $this->pdo->lastInsertId();
        return $this->read($id);
    }

    protected function update($id, $body, $replace)
    {
        $this->assertWritable($id); $data = $this->writableData($body, false); unset($data[$this->primaryKey()]); $this->applyTimestamps($data, false);
        if (!$data) throw new APIException(422, 'payload.empty', 'Aucune donnée inscriptible.');
        $sets = array(); $bindings = array(':pk' => $id); foreach ($data as $column => $value) { $sets[] = $column . '=:' . $column; $bindings[':' . $column] = $value; }
        list($where, $scopeBindings) = $this->writeScope(); $where .= ($where === '' ? ' WHERE ' : ' AND ') . $this->primaryKey() . ' = :pk';
        $stmt = $this->pdo->prepare('UPDATE ' . $this->route['table'] . ' SET ' . implode(',', $sets) . $where); $stmt->execute($bindings + $scopeBindings);
        return $this->read($id);
    }

    protected function delete($id)
    {
        $this->assertWritable($id); list($where, $bindings) = $this->writeScope(); $where .= ($where === '' ? ' WHERE ' : ' AND ') . $this->primaryKey() . ' = :pk'; $bindings[':pk'] = $id;
        $stmt = $this->pdo->prepare('DELETE FROM ' . $this->route['table'] . $where); $stmt->execute($bindings);
        return array('data' => array('id' => $id, 'deleted' => true), 'meta' => array());
    }

    protected function scope()
    {
        $table = $this->route['table']; $uid = (int)$this->user['id'];
        if ($table === 'properties') return array(' WHERE deleted_at IS NULL AND (user_id = :user_id OR visibility = \'shared\')', array(':user_id' => $uid));
        if ($table === 'property_tags' || $table === 'analyses' || $table === 'analysis_jobs') return array(' WHERE property_id IN (SELECT id FROM properties WHERE deleted_at IS NULL AND (user_id = :user_id OR visibility = \'shared\'))', array(':user_id' => $uid));
        return array('', array());
    }

    protected function writeScope()
    {
        $table = $this->route['table']; $uid = (int)$this->user['id'];
        if ($table === 'properties') return array(' WHERE user_id = :user_id', array(':user_id' => $uid));
        return array(' WHERE property_id IN (SELECT id FROM properties WHERE user_id = :user_id AND deleted_at IS NULL)', array(':user_id' => $uid));
    }

    protected function assertWritable($id)
    {
        list($where, $bindings) = $this->writeScope(); $where .= ($where === '' ? ' WHERE ' : ' AND ') . $this->primaryKey() . ' = :pk'; $bindings[':pk'] = $id;
        $stmt = $this->pdo->prepare('SELECT 1 FROM ' . $this->route['table'] . $where . ' LIMIT 1'); $stmt->execute($bindings);
        if (!$stmt->fetchColumn()) throw new APIException(403, 'resource.forbidden', 'Modification de la ressource interdite.');
    }

    protected function writableData($body, $creating)
    {
        if (!is_array($body)) throw new APIException(400, 'json.invalid', 'Corps JSON invalide.');
        $allowed = isset($this->route['writable']) && is_array($this->route['writable']) ? $this->route['writable'] : array(); $result = array();
        foreach ($allowed as $column) if (array_key_exists($column, $body)) $result[$column] = is_array($body[$column]) ? json_encode($body[$column], JSON_UNESCAPED_UNICODE) : $body[$column];
        return $result;
    }
    protected function applyOwnership(&$data)
    {
        if ($this->route['table'] === 'properties') { $data['user_id'] = (int)$this->user['id']; return; }
        if (isset($data['property_id'])) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM properties WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL');
            $stmt->execute(array(':id' => $data['property_id'], ':user_id' => (int)$this->user['id']));
            if (!$stmt->fetchColumn()) throw new APIException(403, 'property.forbidden', 'Modification du bien interdite.');
        }
    }
    protected function applyTimestamps(&$data, $creating) { $now = gmdate('c'); if ($creating && in_array('created_at', $this->columns(), true)) $data['created_at'] = $now; if (in_array('updated_at', $this->columns(), true)) $data['updated_at'] = $now; }
    protected function columns() { static $cache = array(); $table = $this->route['table']; if (!isset($cache[$table])) { $rows = $this->pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(); $cache[$table] = array(); foreach ($rows as $row) $cache[$table][] = $row['name']; } return $cache[$table]; }
    protected function transformRow($row) { foreach ($row as $key => $value) if (substr($key, -5) === '_json' && is_string($value)) { $decoded = json_decode($value, true); if ($decoded !== null) $row[$key] = $decoded; } return $row; }
    protected function primaryKey() { return isset($this->route['primary_key']) ? $this->route['primary_key'] : 'id'; }
    protected function requiredId() { if (!isset($this->params['id'])) throw new APIException(400, 'resource.id_required', 'Identifiant requis.'); return $this->params['id']; }
}
