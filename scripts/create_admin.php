<?php
require_once __DIR__ . '/../app/Database/bootstrap.php';
require_once __DIR__ . '/../app/Services/AuthService.php';
require_once __DIR__ . '/../app/Repositories/UserRepository.php';

sigma_db();

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script doit être lancé en CLI." . PHP_EOL);
    exit(1);
}

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/create_admin.php email mot_de_passe [nom]" . PHP_EOL);
    exit(1);
}

$email = $argv[1];
$password = $argv[2];
$name = isset($argv[3]) ? $argv[3] : 'Administrateur';
$users = new UserRepository(sigma_db());
$existing = $users->findByEmail($email);

if ($existing) {
    $users->updatePlan($existing['id'], 'admin');
    echo json_encode(array('ok' => true, 'updated' => true, 'user_id' => (int)$existing['id'], 'email' => $email, 'plan' => 'admin'), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$auth = new AuthService($users);
$user = $auth->register($email, $password, $name);
$users->updatePlan($user['id'], 'admin');

echo json_encode(array('ok' => true, 'created' => true, 'user_id' => (int)$user['id'], 'email' => $email, 'plan' => 'admin'), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
