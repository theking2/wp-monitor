<?php

use App\Controllers\AuthController;
use App\Controllers\ScanController;
use App\Controllers\SiteController;

/** @var \App\Core\Router $router */

$router->post('/api/login', [new AuthController(), 'login']);

$router->get('/api/sites', [new SiteController(), 'index'], true);
$router->get('/api/sites/{id}', [new SiteController(), 'show'], true);
$router->post('/api/sites', [new SiteController(), 'store'], true);

$router->post('/api/sites/{id}/scan', [new ScanController(), 'scan'], true);
