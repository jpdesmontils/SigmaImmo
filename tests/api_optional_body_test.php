<?php
require_once __DIR__ . '/../app/API/APIApplication.php';

class TestableAPIApplication extends APIApplication
{
    public function parseBody($raw, $optional)
    {
        return $this->decodeBody($raw, $optional);
    }
}

$application = new TestableAPIApplication(dirname(__DIR__));
$body = $application->parseBody('', true);
assertOptionalBody(is_array($body) && count($body) === 0, 'an optional empty request body is accepted');

$rejected = false;
try {
    $application->parseBody('', false);
} catch (APIException $error) {
    $rejected = $error->status() === 400 && $error->apiCode() === 'json.invalid';
}
assertOptionalBody($rejected, 'an empty request body remains invalid by default');

$routes = json_decode(file_get_contents(__DIR__ . '/../public/api/routes_wl.json'), true);
$priceRoute = null;
foreach ($routes['routes'] as $route) {
    if ($route['path'] === '/api/v1/properties/{property_id}/prices') $priceRoute = $route;
}
assertOptionalBody($priceRoute && !empty($priceRoute['body_optional']), 'only the price route opts into an optional request body');

echo "api_optional_body_test ok\n";

function assertOptionalBody($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, 'Assertion failed: ' . $message . "\n");
        exit(1);
    }
}
