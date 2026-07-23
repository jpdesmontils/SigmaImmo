<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
$id = isset($_GET['id']) ? (string)$_GET['id'] : '';
$type = isset($_GET['type']) ? (string)$_GET['type'] : '';
if (!preg_match('/^[A-Za-z0-9_-]{1,180}$/', $id) || !in_array($type, ['locatif', 'mdb'], true)) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'Paramètres invalides']); exit; }
$file = __DIR__ . '/../data/analyses/' . $type . '/' . $id . '.json';
if (!is_file($file)) { http_response_code(404); echo json_encode(['ok' => false, 'error' => 'Analyse introuvable']); exit; }
$analysis = json_decode(file_get_contents($file), true);
if (!is_array($analysis)) { http_response_code(500); echo json_encode(['ok' => false, 'error' => 'Analyse invalide']); exit; }
echo json_encode(['ok' => true, 'analysis' => $analysis], JSON_UNESCAPED_UNICODE);
