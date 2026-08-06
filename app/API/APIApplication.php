<?php
require_once dirname(__DIR__) . '/Database/bootstrap.php';
require_once __DIR__ . '/APIException.php';
require_once dirname(__DIR__) . '/Services/ApiTokenService.php';

class APIApplication
{
    private $root;
    public function __construct($root) { $this->root = rtrim($root, '/'); }
    public function run()
    {
        try {
            $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null; $this->cors($origin);
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); return; }
            $routeData = $this->jsonFile($this->root . '/public/api/routes_wl.json'); $path = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
            $apiPosition = strpos($path, '/api/v1'); if ($apiPosition > 0) $path = substr($path, $apiPosition);
            list($route, $params, $allowed) = $this->match($routeData['routes'], $path, $_SERVER['REQUEST_METHOD']);
            if ($route && isset($route['resource']) && isset($routeData['resources'][$route['resource']])) $route = array_merge($routeData['resources'][$route['resource']], $route);
            if (!$route) { if ($allowed) throw new APIException(405, 'method.not_allowed', 'Méthode non autorisée.'); throw new APIException(404, 'route.not_found', 'Route introuvable.'); }
            $user = $this->authenticate(isset($route['auth']) ? $route['auth'] : 'token');
            $class = $route['processor']; $file = __DIR__ . '/' . $class . '.php'; if (!is_file($file)) throw new APIException(500, 'route.invalid', 'Processeur absent.'); require_once $file;
            $body = null; if (in_array($_SERVER['REQUEST_METHOD'], array('POST','PUT','PATCH'), true)) { $raw = file_get_contents('php://input'); $body = json_decode($raw, true); if (!is_array($body)) throw new APIException(400, 'json.invalid', 'Corps JSON invalide.'); }
            $processor = new $class(sigma_db(), $route, $user, $params); $result = $processor->process($_SERVER['REQUEST_METHOD'], $body);
            $this->respond(200, isset($result['data']) ? $result['data'] : null, null, isset($result['meta']) ? $result['meta'] : array());
        } catch (APIException $e) { $this->respond($e->status(), null, array('code' => $e->apiCode(), 'message' => $e->getMessage(), 'details' => $e->details()), array()); }
        catch (Throwable $e) { $this->respond(500, null, array('code' => 'internal_error', 'message' => 'Erreur interne.', 'details' => null), array()); }
    }
    private function match($routes, $path, $method)
    {
        $allowed = false; foreach ($routes as $route) { $names = array(); $pattern = preg_replace_callback('/\{([a-z_]+)\}/', function($m) use (&$names) { $names[] = $m[1]; return '([^/]+)'; }, $route['path']); if (!preg_match('#^' . $pattern . '$#', $path, $matches)) continue; $allowed = true; if (!in_array($method, $route['methods'], true)) continue; array_shift($matches); $params = array(); foreach ($names as $i => $name) $params[$name] = rawurldecode($matches[$i]); return array($route, $params, true); } return array(null, array(), $allowed);
    }
    private function authenticate($mode)
    {
        if ($mode === 'session') { require_once dirname(__DIR__) . '/Middleware/CurrentUserMiddleware.php'; $user = sigma_current_user(); if ($user) return $user; }
        if ($mode === 'user') { require_once dirname(__DIR__) . '/Middleware/CurrentUserMiddleware.php'; $user = sigma_current_user(); if ($user) return $user; }
        $header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : ''; if (preg_match('/^Bearer\s+(.+)$/i', $header, $match)) { $user = (new ApiTokenService())->authenticate(trim($match[1])); if ($user) return $user; }
        throw new APIException(401, 'auth.unauthorized', 'Token absent ou invalide.');
    }
    private function cors($origin)
    {
        if (!$origin) return; $config = $this->jsonFile($this->root . '/public/api/CORS.json'); if (!in_array($origin, $config['allowed_origins'], true)) throw new APIException(403, 'cors.origin_forbidden', 'Origine CORS interdite.');
        header('Access-Control-Allow-Origin: ' . $origin); header('Vary: Origin'); header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS'); header('Access-Control-Allow-Headers: Authorization, Content-Type');
    }
    private function jsonFile($path) { $data = is_file($path) ? json_decode(file_get_contents($path), true) : null; if (!is_array($data)) throw new APIException(500, 'configuration.invalid', 'Configuration API invalide.'); return $data; }
    private function respond($status, $data, $error, $meta) { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode(array('data' => $data, 'error' => $error, 'meta' => (object)$meta), JSON_UNESCAPED_UNICODE); }
}
