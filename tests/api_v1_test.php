<?php
$tmp = tempnam(sys_get_temp_dir(), 'sigma_api_'); if ($tmp === false) exit(1); unlink($tmp); putenv('SIGMAIMMO_SQLITE_PATH=' . $tmp);
require_once __DIR__ . '/../app/Database/bootstrap.php';
require_once __DIR__ . '/../app/Services/AuthService.php';
require_once __DIR__ . '/../app/Services/ApiTokenService.php';
require_once __DIR__ . '/../app/Repositories/ApiTokenRepository.php';
require_once __DIR__ . '/../app/Repositories/PropertyRepository.php';
require_once __DIR__ . '/../app/API/cAPI_Processor.php';
require_once __DIR__ . '/../app/API/PropertyAPIProcessor.php';

$pdo = sigma_db(); $auth = new AuthService(); $owner = $auth->register('owner@example.test', 'password123'); $other = $auth->register('other@example.test', 'password123');
$tokenService = new ApiTokenService(new ApiTokenRepository($pdo)); $issued = $tokenService->issue($owner['id'], 'test');
assertApi(strlen($issued['token']) === 64, 'token is returned once with 256 bits');
assertApi($tokenService->authenticate($issued['token'])['id'] === (int)$owner['id'], 'hashed token authenticates its owner');
assertApi($tokenService->authenticate(str_repeat('0', 64)) === null, 'unknown token is rejected');
$properties = new PropertyRepository($pdo); $properties->upsert(array('id'=>'shared-one','title'=>'Shared'), $owner); $properties->upsert(array('id'=>'private-one','title'=>'Private','visibility'=>'private'), $owner);
$route = array('table'=>'properties','primary_key'=>'id','writable'=>array('id','title','visibility'));
$reader = new cAPI_Processor($pdo, $route, $other, array('id'=>'shared-one')); $read = $reader->process('GET', null); assertApi($read['data']['id'] === 'shared-one', 'shared property is visible to another authenticated user');
$hidden = false; try { (new cAPI_Processor($pdo, $route, $other, array('id'=>'private-one')))->process('GET', null); } catch (APIException $e) { $hidden = $e->status() === 404; } assertApi($hidden, 'private property is hidden from another user');
$propertyReader = new PropertyAPIProcessor($pdo, $route, $other); $propertyList = $propertyReader->process('GET', null); assertApi(count($propertyList['data']) === 1 && $propertyList['data'][0]['id'] === 'shared-one', 'property collection includes another user shared property but excludes their private property');
$propertyDetail = (new PropertyAPIProcessor($pdo, $route, $other, array('id'=>'shared-one')))->process('GET', null); assertApi($propertyDetail['data']['listing']['id'] === 'shared-one', 'property detail uses the same shared visibility scope as the collection');
$blocked = false; try { (new cAPI_Processor($pdo, $route, $other, array('id'=>'shared-one')))->process('PATCH', array('title'=>'stolen')); } catch (APIException $e) { $blocked = $e->status() === 403; } assertApi($blocked, 'shared property remains writable only by its owner');
$created = (new cAPI_Processor($pdo, $route, $other))->process('POST', array('id'=>'other-one','title'=>'Other','visibility'=>'private')); assertApi($created['data']['user_id'] === (int)$other['id'], 'generic create assigns ownership');
$routes = json_decode(file_get_contents(__DIR__ . '/../api/v1/routes_wl.json'), true); assertApi(is_array($routes) && count($routes['routes']) >= 6, 'route whitelist declares API v1 resources');
unlink($tmp); echo "api_v1_test ok\n";
function assertApi($condition, $message) { if (!$condition) { fwrite(STDERR, 'Assertion failed: ' . $message . "\n"); exit(1); } }
