<?php
// ============================================================
// ImmoAggregator — API tag.php
// Met à jour le champ selection d'une annonce
// ============================================================

require_once __DIR__ . '/../app/Database/bootstrap.php';
require_once __DIR__ . '/../app/Repositories/PropertyRepository.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }


$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data || empty($data['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing id']);
    exit;
}

$id  = (string)$data['id'];
if (!preg_match('/^[A-Za-z0-9_-]{1,180}$/', $id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid id']);
    exit;
}
$sel = isset($data['selection']) ? $data['selection'] : null;

// Valider la valeur
if ($sel !== null && !in_array($sel, ['shortlist', 'ecartee', 'a_visiter', 'visite'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid selection value']);
    exit;
}

$updated = (new PropertyRepository(sigma_db()))->updateFields($id, array('selection' => $sel));

echo json_encode(array('ok' => true, 'id' => $id, 'selection' => $sel, 'updated' => $updated));
