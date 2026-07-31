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

// NOTE: we deliberately do NOT store the opening user's AUTH_ID here. Data is read
// with the installer's (admin) service token so every viewer sees the same records;
// the opening user's AUTH_ID is used only to identify them + their role (App::
// resolveUser), never to replace the service token. Storing it would clobber the
// service token with a limited user's permissions and blank the screens.

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
