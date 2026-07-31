<?php

declare(strict_types=1);

/**
 * Tests the approval logic without a portal: role ranking, amount-band routing
 * (Authority), and the tamper-proof signed session token.
 *
 * Run: php capex-app/tests/ApprovalsTest.php
 */

require __DIR__ . '/../src/Autoload.php';

use Capex\Domain\Authority;
use Capex\Domain\Roles;
use Capex\Service\Session;

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
        echo "  FAIL- {$label}\n        expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
    }
}

// ── Roles::meets ────────────────────────────────────────────────────────────
check('HOD meets HOD gate', true, Roles::meets(Roles::HOD, Roles::HOD));
check('CFO meets HOD gate (higher stands in)', true, Roles::meets(Roles::GROUP_CFO, Roles::HOD));
check('HOD cannot meet CFO gate', false, Roles::meets(Roles::HOD, Roles::GROUP_CFO));
check('Requester cannot approve anything', false, Roles::meets(Roles::REQUESTER, Roles::HOD));
check('System Admin never approves', false, Roles::meets(Roles::SYSTEM_ADMIN, Roles::HOD));

// ── Capabilities (closed by default) ────────────────────────────────────────
check('unlisted user (NONE) cannot open', false, Roles::canOpen(Roles::NONE));
check('Viewer can open', true, Roles::canOpen(Roles::VIEWER));
check('Requester can open', true, Roles::canOpen(Roles::REQUESTER));

check('Viewer cannot submit', false, Roles::canSubmit(Roles::VIEWER));
check('Requester can submit', true, Roles::canSubmit(Roles::REQUESTER));
check('CFO can submit', true, Roles::canSubmit(Roles::GROUP_CFO));
check('System Admin cannot submit', false, Roles::canSubmit(Roles::SYSTEM_ADMIN));

check('Requester is not an approver', false, Roles::canApprove(Roles::REQUESTER));
check('HOD is an approver', true, Roles::canApprove(Roles::HOD));
check('System Admin is not an approver', false, Roles::canApprove(Roles::SYSTEM_ADMIN));

check('only Regional Finance/CFO edit targets — Carol yes', true, Roles::canEditTargets(Roles::REGIONAL_FIN));
check('Group CFO edits targets', true, Roles::canEditTargets(Roles::GROUP_CFO));
check('HOD cannot edit targets', false, Roles::canEditTargets(Roles::HOD));
check('Country MD cannot edit targets', false, Roles::canEditTargets(Roles::COUNTRY_MD));

// ── Authority::forAmount (amount bands, no budget) ──────────────────────────
$bands = [5_000_000 => 'HOD', 25_000_000 => 'REGIONAL_FIN', 100_000_000 => 'COUNTRY_MD'];
check('40k -> HOD', 'HOD', Authority::forAmount(4_000_000, $bands));
check('exactly 50k -> HOD', 'HOD', Authority::forAmount(5_000_000, $bands));
check('150k -> Regional Finance', 'REGIONAL_FIN', Authority::forAmount(15_000_000, $bands));
check('500k -> Country MD', 'COUNTRY_MD', Authority::forAmount(50_000_000, $bands));
check('5m -> Group CFO (above bands)', 'GROUP_CFO', Authority::forAmount(500_000_000, $bands));

// Compose: can a Regional Finance clear a 150k request?
check('Regional Finance clears 150k', true, Roles::meets(Roles::REGIONAL_FIN, Authority::forAmount(15_000_000, $bands)));
check('HOD cannot clear 150k', false, Roles::meets(Roles::HOD, Authority::forAmount(15_000_000, $bands)));
check('HOD cannot clear 5m', false, Roles::meets(Roles::HOD, Authority::forAmount(500_000_000, $bands)));
check('Group CFO clears 5m', true, Roles::meets(Roles::GROUP_CFO, Authority::forAmount(500_000_000, $bands)));

// ── Session: tamper-proof identity ──────────────────────────────────────────
$s = new Session('super-secret-key');
$now = 1_700_000_000;
$tok = $s->issue(144, Roles::GROUP_CFO, $now);

$ok = $s->verify($tok, $now + 10);
check('valid token -> right user', 144, $ok['id'] ?? null);
check('valid token -> role', Roles::GROUP_CFO, $ok['role'] ?? null);
check('expired token rejected', null, $s->verify($tok, $now + 4000));

$parts = explode('.', $tok);
check('forged uid rejected', null, $s->verify('999.' . $parts[1] . '.' . $parts[2] . '.' . $parts[3], $now + 10));
check('token from another secret rejected', null,
    $s->verify((new Session('different'))->issue(144, Roles::GROUP_CFO, $now), $now + 10));

echo "\n{$tests} checks, {$failures} failure(s)\n";
exit($failures === 0 ? 0 : 1);
