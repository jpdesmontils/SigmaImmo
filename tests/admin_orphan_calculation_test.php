<?php
$tmp = tempnam(sys_get_temp_dir(), 'sigma_admin_orphan_');
if ($tmp === false) exit(1);
unlink($tmp);
putenv('SIGMAIMMO_SQLITE_PATH=' . $tmp);

require_once __DIR__ . '/../app/Database/bootstrap.php';
require_once __DIR__ . '/../app/Services/AuthService.php';
require_once __DIR__ . '/../app/Repositories/PropertyRepository.php';
require_once __DIR__ . '/../app/API/cAPI_Processor.php';

class CalculationAuthorizationProcessor extends cAPI_Processor
{
    public function calculationAllowed($propertyId)
    {
        $this->assertPropertyCalculationAllowed($propertyId);
        return true;
    }
}

$pdo = sigma_db();
$auth = new AuthService();
$admin = $auth->register('admin@example.test', 'password123');
$user = $auth->register('user@example.test', 'password123');
$owner = $auth->register('owner@example.test', 'password123');
$pdo->prepare("UPDATE users SET plan = 'admin' WHERE id = :id")->execute(array(':id' => $admin['id']));
$admin['plan'] = 'admin';

$properties = new PropertyRepository($pdo);
$properties->upsert(array('id' => 'orphan', 'title' => 'Imported orphan'));
$properties->upsert(array('id' => 'owned', 'title' => 'Owned property'), $owner);
$route = array('table' => 'analysis_jobs');

assertCalculation((new CalculationAuthorizationProcessor($pdo, $route, $admin))->calculationAllowed('orphan'), 'an administrator can calculate an orphan property');
assertForbiddenCalculation(new CalculationAuthorizationProcessor($pdo, $route, $user), 'orphan', 'a non-administrator cannot calculate an orphan property');
assertCalculation((new CalculationAuthorizationProcessor($pdo, $route, $owner))->calculationAllowed('owned'), 'an owner can still calculate their property');
assertForbiddenCalculation(new CalculationAuthorizationProcessor($pdo, $route, $admin), 'owned', 'an administrator cannot calculate another user property');

$orphan = $properties->find('orphan');
assertCalculation(array_key_exists('userId', $orphan) && $orphan['userId'] === null, 'calculation authorization keeps the orphan user_id null');

unlink($tmp);
echo "admin_orphan_calculation_test ok\n";

function assertForbiddenCalculation($processor, $propertyId, $message)
{
    try {
        $processor->calculationAllowed($propertyId);
    } catch (APIException $error) {
        assertCalculation($error->status() === 403, $message);
        return;
    }
    assertCalculation(false, $message);
}

function assertCalculation($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, 'Assertion failed: ' . $message . "\n");
        exit(1);
    }
}
