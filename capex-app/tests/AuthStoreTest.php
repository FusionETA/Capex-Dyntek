<?php

declare(strict_types=1);

/**
 * Tests the SQLite token store + refresh logic in Capex\Bitrix\Auth without any
 * network I/O — the HTTP refresh is replaced by an injected fake. Run:
 *
 *   php capex-app/tests/AuthStoreTest.php
 *
 * Uses a throwaway sqlite file under the system temp dir; cleaned up at the end.
 */

require __DIR__ . '/../src/Autoload.php';

use Capex\Bitrix\Auth;

$tests = 0;
$failures = 0;

/** @param mixed $expected @param mixed $actual */
function check(string $label, $expected, $actual): void
{
    global $tests, $failures;
    $tests++;
    if ($expected === $actual) {
        echo "  ok  - {$label}\n";
    } else {
        $failures++;
        echo "  FAIL- {$label}\n";
        echo '        expected: ' . var_export($expected, true) . "\n";
        echo '        actual:   ' . var_export($actual, true) . "\n";
    }
}

function fail(string $label, string $why): void
{
    global $tests, $failures;
    $tests++;
    $failures++;
    echo "  FAIL- {$label}\n        {$why}\n";
}

$store = tempnam(sys_get_temp_dir(), 'capex_tokens_') . '.sqlite';

// Fake refresher: records the refresh_token it was handed, returns a fresh bundle.
$handed = null;
$refresher = function (string $rt) use (&$handed): array {
    $handed = $rt;
    return [
        'access_token'  => 'ACCESS_NEW',
        'refresh_token' => 'REFRESH_NEW',
        'expires_in'    => 3600,
        'member_id'     => 'portalABC',
    ];
};

$auth = new Auth('local.app', 'secret', $store, $refresher);

// --- store + isInstalledPortal ---
$auth->store('ACCESS_1', 'REFRESH_1', time() + 3600, 'portalABC');
check('installed portal is recognised', true, $auth->isInstalledPortal('portalABC'));
check('unknown portal is rejected', false, $auth->isInstalledPortal('portalXYZ'));

// --- accessToken returns the stored, still-valid token (no refresh) ---
$handed = null;
check('valid token returned as-is', 'ACCESS_1', $auth->accessToken());
check('no refresh happened for a valid token', null, $handed);

// --- expired token triggers a refresh via the injected refresher ---
$auth->store('ACCESS_1', 'REFRESH_1', time() - 10, 'portalABC'); // already expired
$token = $auth->accessToken();
check('expired token triggers refresh -> new access token', 'ACCESS_NEW', $token);
check('refresher received the stored refresh token', 'REFRESH_1', $handed);

// --- the refreshed bundle was persisted (next call is valid, no further refresh) ---
$handed = null;
check('refreshed token now returned without refreshing again', 'ACCESS_NEW', $auth->accessToken());
check('no second refresh for the freshly stored token', null, $handed);

// --- useMember rejects an unknown portal ---
try {
    $auth->useMember('portalXYZ');
    fail('useMember rejects unknown portal', 'expected a RuntimeException');
} catch (\RuntimeException $e) {
    check('useMember rejects unknown portal', true, true);
}

@unlink($store);

echo "\n{$tests} checks, {$failures} failure(s)\n";
exit($failures === 0 ? 0 : 1);
