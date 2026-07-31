<?php

declare(strict_types=1);

/**
 * Tests the approval logic without a portal: role ranking, the signed session
 * token (tamper-proof identity), and the authority-band gate routing.
 *
 * Run: php capex-app/tests/ApprovalsTest.php
 */

require __DIR__ . '/../src/Autoload.php';

use Capex\Domain\BudgetEngine;
use Capex\Domain\Roles;
use Capex\Domain\Verdict;
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
check('Regional Finance meets its own gate', true, Roles::meets(Roles::REGIONAL_FIN, Roles::REGIONAL_FIN));

// ── authority band routing (the Finance gate) ───────────────────────────────
$bands = [5_000_000 => 'HOD', 25_000_000 => 'REGIONAL_FIN', 100_000_000 => 'COUNTRY_MD'];
$within = new Verdict(Verdict::WITHIN, 0);
$over = new Verdict(Verdict::OVER, 1);
check('40k within -> HOD authority', 'HOD', BudgetEngine::authorityFor(4_000_000, $within, $bands));
check('150k within -> Regional Finance', 'REGIONAL_FIN', BudgetEngine::authorityFor(15_000_000, $within, $bands));
check('500k within -> Country MD', 'COUNTRY_MD', BudgetEngine::authorityFor(50_000_000, $within, $bands));
check('5m within -> Group CFO (above bands)', 'GROUP_CFO', BudgetEngine::authorityFor(500_000_000, $within, $bands));
check('small OVER -> Group CFO', 'GROUP_CFO', BudgetEngine::authorityFor(1_000_000, $over, $bands));

// Compose: can a Regional Finance approve a 150k within request at the Finance gate?
$required = BudgetEngine::authorityFor(15_000_000, $within, $bands);
check('Regional Finance can clear a 150k gate', true, Roles::meets(Roles::REGIONAL_FIN, $required));
check('HOD cannot clear a 150k gate', false, Roles::meets(Roles::HOD, $required));
// OVER always needs CFO
$req2 = BudgetEngine::authorityFor(1_000_000, $over, $bands);
check('Regional Finance cannot clear an OVER gate', false, Roles::meets(Roles::REGIONAL_FIN, $req2));
check('Group CFO clears an OVER gate', true, Roles::meets(Roles::GROUP_CFO, $req2));

// ── Session: tamper-proof identity ──────────────────────────────────────────
$s = new Session('super-secret-key');
$now = 1_700_000_000;
$tok = $s->issue(144, Roles::GROUP_CFO, $now);

$ok = $s->verify($tok, $now + 10);
check('valid token verifies to the right user', 144, $ok['id'] ?? null);
check('valid token carries the role', Roles::GROUP_CFO, $ok['role'] ?? null);

check('expired token rejected', null, $s->verify($tok, $now + 4000));

// Tamper: bump the role to CFO with the original signature -> must fail.
$parts = explode('.', $tok);
$forged = $parts[0] . '.' . Roles::GROUP_CFO . '.' . $parts[2] . '.' . $parts[3];
$otherSecretTok = (new Session('different-secret'))->issue(144, Roles::REQUESTER, $now);
check('token signed with another secret rejected', null, $s->verify($otherSecretTok, $now + 10));

// Forge attempt: change uid, keep signature -> fail.
$forged2 = '999.' . $parts[1] . '.' . $parts[2] . '.' . $parts[3];
check('forged uid rejected', null, $s->verify($forged2, $now + 10));

echo "\n{$tests} passed-or-failed; " . ($failures) . " failure(s)\n";
exit($failures === 0 ? 0 : 1);
