<?php

require __DIR__ . '/api/bootstrap.php';

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

header('Access-Control-Allow-Origin: ' . (getenv('CORS_ALLOWED_ORIGIN') ?: '*'));
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router = new Router();
require __DIR__ . '/api/routes.php';

try {
    $router->dispatch(new Request());
} catch (\Throwable $e) {
    Logger::get()->error('Unhandled exception', ['exception' => $e]);
    Response::error('Internal server error', 500);
}
