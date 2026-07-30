<?php

declare(strict_types=1);

/**
 * Install handler. Bitrix posts the auth bundle here (AUTH_ID, REFRESH_ID,
 * AUTH_EXPIRES, member_id). We persist the tokens server-side, then render a
 * BX24-powered page that provisions the SPAs FROM THE BROWSER.
 *
 * Why the browser: creating SPA user fields requires an interactive admin session
 * (userfieldconfig.add checks CUserTypeEntity admin rights that an OAuth server
 * token doesn't carry). BX24.callMethod runs in the installing admin's session, so
 * it's allowed. See provision.js and the README "How provisioning works".
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

// Persist tokens so the server can later run read-only discovery + serve the app.
$app->auth->store($accessToken, $refreshToken, time() + $expiresIn, $memberId);

// Schema drives the browser provisioning; handler base drives placement/event binds.
$schema = require __DIR__ . '/../config/schema.php';
$host   = ($_SERVER['HTTP_HOST'] ?? '');
$dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/install.php')), '/');
// Default https (Bitrix requires https handlers; proxies may not set HTTPS).
$isLocal = (bool) preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/', $host);
$scheme  = $isLocal ? 'http' : (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') ?: 'https');
$handlerBase = sprintf('%s://%s%s', $scheme, $host, $dir); // .../public

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Capex — install</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($handlerBase, ENT_QUOTES) ?>/assets/app.css">
    <script src="//api.bitrix24.com/api/v1/"></script>
</head>
<body>
    <main class="capex-install">
        <h1>Installing Capex</h1>
        <p class="muted">Creating the Smart Processes, fields and stages in your portal…</p>
        <ol id="capex-log" class="capex-log"></ol>
        <p id="capex-done" hidden><strong>Done.</strong> Finishing up…</p>
    </main>

    <script>
        window.CAPEX = {
            schema: <?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
            handlerBase: <?= json_encode($handlerBase, JSON_UNESCAPED_SLASHES) ?>
        };
    </script>
    <script src="<?= htmlspecialchars($handlerBase, ENT_QUOTES) ?>/assets/provision.js"></script>
</body>
</html>
