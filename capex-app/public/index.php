<?php

declare(strict_types=1);

/**
 * App entry. Bitrix24 posts AUTH_ID here on install/open, and placements load
 * their screens through this front controller.
 */

require __DIR__ . '/../src/Autoload.php';

$config = require __DIR__ . '/../config/app.php';

use Capex\Http\Router;
use Capex\Http\Handlers\Dashboard;
use Capex\Http\Handlers\Webhook;

$router = new Router();

// Screens (placements) — each handler re-checks Bitrix rights server-side.
$router->add('/', static fn () => (new Dashboard())->handle());
$router->add('/dashboard', static fn () => (new Dashboard())->handle());
$router->add('/webhook', static fn () => (new Webhook())->handle());

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$router->dispatch($path);
