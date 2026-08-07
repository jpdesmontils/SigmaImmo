<?php
require_once dirname(__DIR__) . '/Database/Connection.php';

class UserRepository
{
    private $pdo;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?: DatabaseConnection::get();
    }

    public function find($id)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(array(':id' => (int)$id));
        $row = $stmt->fetch();
        return $row ? $row : null;
    }

    public function findByEmail($email)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE lower(email) = lower(:email) LIMIT 1');
        $stmt->execute(array(':email' => trim((string)$email)));
        $row = $stmt->fetch();
        return $row ? $row : null;
    }

    public function create($email, $passwordHash, $plan, $name)
    {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash, plan, name, created_at, updated_at) VALUES (:email, :password_hash, :plan, :name, :created_at, :updated_at)');
        $stmt->execute(array(
            ':email' => trim((string)$email),
            ':password_hash' => $passwordHash,
            ':plan' => $plan,
            ':name' => $name,
            ':created_at' => $now,
            ':updated_at' => $now
        ));
        return $this->find((int)$this->pdo->lastInsertId());
    }

    public function updatePlan($id, $plan)
    {
        $stmt = $this->pdo->prepare('UPDATE users SET plan = :plan, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(array(':id' => (int)$id, ':plan' => $plan, ':updated_at' => gmdate('c')));
        return $stmt->rowCount() > 0;
    }

    public function updatePasswordHash($id, $passwordHash)
    {
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(array(':id' => (int)$id, ':password_hash' => $passwordHash, ':updated_at' => gmdate('c')));
        return $stmt->rowCount() > 0;
    }

    public function touchLastLogin($id)
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(array(':id' => (int)$id, ':last_login_at' => gmdate('c'), ':updated_at' => gmdate('c')));
    }

    public function ensureLegacyImportUser()
    {
        $existing = $this->findByEmail('legacy/import');
        if ($existing) return $existing;
        return $this->create('legacy/import', null, 'admin', 'Legacy import');
    }

    public function search($query, $plan, $limit, $offset)
    {
        list($sql, $params) = $this->searchWhere($query, $plan);
        $stmt = $this->pdo->prepare('SELECT * FROM users' . $sql . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function count($query, $plan)
    {
        list($sql, $params) = $this->searchWhere($query, $plan);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users' . $sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function countByPlan()
    {
        $counts = array('free' => 0, 'paid' => 0, 'admin' => 0);
        $stmt = $this->pdo->query("SELECT plan, COUNT(*) AS n FROM users GROUP BY plan");
        foreach ($stmt->fetchAll() as $row) {
            if (isset($counts[$row['plan']])) {
                $counts[$row['plan']] = (int)$row['n'];
            }
        }
        return $counts;
    }

    private function searchWhere($query, $plan)
    {
        $conditions = array();
        $params = array();
        $query = trim((string)$query);
        if ($query !== '') {
            $conditions[] = '(lower(email) LIKE :q OR lower(name) LIKE :q)';
            $params[':q'] = '%' . strtolower($query) . '%';
        }
        if (in_array($plan, array('free', 'paid', 'admin'), true)) {
            $conditions[] = 'plan = :plan';
            $params[':plan'] = $plan;
        }
        $sql = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        return array($sql, $params);
    }
}
