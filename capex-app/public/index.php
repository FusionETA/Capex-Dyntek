<?php

declare(strict_types=1);

/**
 * App entry. Bitrix24 posts AUTH_ID here on install/open, and placements load
 * their screens through this front controller.
 *
 * Routing: system endpoints go by PATH_INFO (…/index.php/webhook, /diag);
 * user-facing screens go by ?screen=dashboard|budget|targets (default dashboard).
 */

require __DIR__ . '/../src/Autoload.php';

use Capex\App;
use Capex\Http\Handlers\Approvals;
use Capex\Http\Handlers\Dashboard;
use Capex\Http\Handlers\Diag;
use Capex\Http\Handlers\NewRequest;
use Capex\Http\Handlers\Targets;

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

// System endpoint (health check) by PATH_INFO, independent of deploy sub-path.
$path = '/' . ltrim((string) ($_SERVER['PATH_INFO'] ?? ''), '/');
if ($path === '/diag') {
    (new Diag($app))->handle();
    return;
}

// User-facing screens.
$screen = (string) ($_REQUEST['screen'] ?? 'dashboard');
match ($screen) {
    'targets'   => (new Targets($app))->handle(),
    'approvals' => (new Approvals($app))->handle(),
    'new'       => (new NewRequest($app))->handle(),
    default     => (new Dashboard($app))->handle(),
};
