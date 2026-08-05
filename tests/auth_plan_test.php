<?php
$tmp = tempnam(sys_get_temp_dir(), 'sigma_auth_');
if ($tmp === false) {
    fwrite(STDERR, "Unable to create temp db\n");
    exit(1);
}
unlink($tmp);
putenv('SIGMAIMMO_SQLITE_PATH=' . $tmp);

require_once __DIR__ . '/../app/Database/bootstrap.php';
require_once __DIR__ . '/../app/Services/AuthService.php';
require_once __DIR__ . '/../app/Repositories/UserRepository.php';
require_once __DIR__ . '/../app/Repositories/PropertyRepository.php';
require_once __DIR__ . '/../app/Services/PlanService.php';

sigma_db();
$users = new UserRepository(sigma_db());
$auth = new AuthService($users);
$properties = new PropertyRepository(sigma_db());

$free = $auth->register('free@example.test', 'password123', 'Free User');
assertTrue($free['plan'] === 'free', 'new users default to free plan');
assertTrue(!empty($free['password_hash']) && $free['password_hash'] !== 'password123', 'password is hashed');
$logged = $auth->login('free@example.test', 'password123');
assertTrue((int)$logged['id'] === (int)$free['id'], 'registered user can login');

for ($i = 1; $i <= PlanService::FREE_FAVORITES_LIMIT; $i++) {
    $properties->upsert(array('id' => 'free-' . $i, 'title' => 'Free ' . $i), $free);
}
assertTrue($properties->countActiveByUser($free['id']) === PlanService::FREE_FAVORITES_LIMIT, 'free user can create ten favorites');
$quotaBlocked = false;
try {
    $properties->upsert(array('id' => 'free-11', 'title' => 'Free 11'), $free);
} catch (RuntimeException $e) {
    $quotaBlocked = $e->getMessage() === 'property.quota_reached';
}
assertTrue($quotaBlocked, 'free user cannot create an eleventh favorite');

$paid = $auth->register('paid@example.test', 'password123', 'Paid User');
$users->updatePlan($paid['id'], 'paid');
$paid = $users->find($paid['id']);
for ($i = 1; $i <= 11; $i++) {
    $properties->upsert(array('id' => 'paid-' . $i, 'title' => 'Paid ' . $i), $paid);
}
assertTrue($properties->countActiveByUser($paid['id']) === 11, 'paid user can create more than ten favorites');
assertTrue($properties->find('paid-1', $paid['id'])['visibility'] === 'private', 'paid favorites are private by default');
assertTrue($properties->find('free-1', $free['id'])['visibility'] === 'shared', 'free favorites are shared by default');
assertTrue($properties->find('paid-1', $free['id']) === null, 'user cannot access another user private favorite via scoped lookup');

$legacy = $users->ensureLegacyImportUser();
assertTrue($legacy['email'] === 'legacy/import' && $legacy['plan'] === 'admin', 'legacy import user exists as admin');

unlink($tmp);
echo "auth_plan_test ok\n";

function assertTrue($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: " . $message . "\n");
        exit(1);
    }
}
