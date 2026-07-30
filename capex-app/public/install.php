<?php

declare(strict_types=1);

/**
 * Placement registration on first install.
 *
 * Bitrix24 posts the initial auth bundle here (AUTH_ID, REFRESH_ID, AUTH_EXPIRES,
 * member_id). We persist the tokens, then register the left-menu placements for
 * the three screens and the onCrmDynamicItemUpdate event -> /webhook.
 */

require __DIR__ . '/../src/Autoload.php';

use Capex\App;

$app = App::boot();

$accessToken  = (string) ($_REQUEST['AUTH_ID'] ?? '');
$refreshToken = (string) ($_REQUEST['REFRESH_ID'] ?? '');
$expiresIn    = (int) ($_REQUEST['AUTH_EXPIRES'] ?? 3600);
$memberId     = (string) ($_REQUEST['member_id'] ?? '');

if ($accessToken === '' || $refreshToken === '' || $memberId === '') {
    http_response_code(400);
    echo 'Missing auth bundle — install must be initiated from Bitrix24.';
    return;
}

// 1. Persist the token bundle.
$app->auth->store($accessToken, $refreshToken, time() + $expiresIn, $memberId);

// 2. Register placements + the update event. Guarded so a re-install is idempotent.
$base = sprintf('%s://%s', $_SERVER['REQUEST_SCHEME'] ?? 'https', $_SERVER['HTTP_HOST'] ?? '');
$req  = (int) ($app->config['entities']['request'] ?? 0);

$placements = [
    ['CRM_DYNAMIC_' . $req . '_LIST_MENU', '/?screen=dashboard', 'Capex Dashboard'],
    ['LEFT_MENU',                          '/?screen=budget',    'Capex Budget'],
    ['LEFT_MENU',                          '/?screen=targets',   'Capex Targets'],
];

foreach ($placements as [$placement, $path, $title]) {
    try {
        $app->client->call('placement.bind', [
            'PLACEMENT' => $placement,
            'HANDLER'   => $base . '/index.php' . $path,
            'TITLE'     => $title,
        ]);
    } catch (\Throwable $e) {
        // Already bound, or entity id not yet configured — safe to ignore on install.
        error_log('placement.bind ' . $placement . ': ' . $e->getMessage());
    }
}

try {
    $app->client->call('event.bind', [
        'event'   => 'onCrmDynamicItemUpdate',
        'handler' => $base . '/index.php/webhook',
    ]);
} catch (\Throwable $e) {
    error_log('event.bind onCrmDynamicItemUpdate: ' . $e->getMessage());
}

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset="utf-8"><p>Capex app installed.</p>';
