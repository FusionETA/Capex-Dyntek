<?php

declare(strict_types=1);

/**
 * App entry. Bitrix24 posts AUTH_ID here on install/open, and placements load
 * their screens through this front controller.
 */

require __DIR__ . '/../src/Autoload.php';

use Capex\App;
use Capex\Http\Router;
use Capex\Http\Handlers\Dashboard;
use Capex\Http\Handlers\Diag;
use Capex\Http\Handlers\Webhook;

$app = App::boot();

// If Bitrix re-posts an auth bundle on app-open, keep the token store fresh.
if (!empty($_REQUEST['AUTH_ID']) && !empty($_REQUEST['REFRESH_ID']) && !empty($_REQUEST['member_id'])) {
    $app->auth->store(
        (string) $_REQUEST['AUTH_ID'],
        (string) $_REQUEST['REFRESH_ID'],
        time() + (int) ($_REQUEST['AUTH_EXPIRES'] ?? 3600),
        (string) $_REQUEST['member_id'],
    );
}

$router = new Router();

// Screens (placements) — each handler re-checks Bitrix rights server-side.
$router->add('/', static fn () => (new Dashboard())->handle());
$router->add('/dashboard', static fn () => (new Dashboard())->handle());
$router->add('/webhook', static fn () => (new Webhook($app))->handle());
$router->add('/diag', static fn () => (new Diag($app))->handle());

// Support both PATH_INFO (…/index.php/webhook) and a plain path.
$path = $_SERVER['PATH_INFO'] ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . ltrim((string) $path, '/');
if (str_ends_with($path, '/index.php')) {
    $path = '/';
}

$router->dispatch($path);
