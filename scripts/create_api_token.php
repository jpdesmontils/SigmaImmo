<?php
require_once __DIR__ . '/../app/Database/bootstrap.php';
require_once __DIR__ . '/../app/Repositories/UserRepository.php';
require_once __DIR__ . '/../app/Services/ApiTokenService.php';
if ($argc < 2) { fwrite(STDERR, "Usage: php scripts/create_api_token.php <email> [libellé]\n"); exit(1); }
$user = (new UserRepository(sigma_db()))->findByEmail($argv[1]);
if (!$user) { fwrite(STDERR, "Utilisateur introuvable.\n"); exit(1); }
$issued = (new ApiTokenService())->issue($user['id'], isset($argv[2]) ? $argv[2] : 'CLI admin');
echo "Token créé (affiché une seule fois): " . $issued['token'] . "\n";
