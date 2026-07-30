<?php

declare(strict_types=1);

/**
 * Zero-dependency test runner for the pure Domain layer. Run:
 *
 *   php capex-app/tests/BudgetEngineTest.php
 *
 * No PHPUnit — keeps the cPanel footprint at nil. Extend with the M3 cases:
 * within / exactly-at / over budget, FX conversion, replayed webhook,
 * rejection releasing a commitment.
 */

require __DIR__ . '/../src/Autoload.php';

use Capex\Domain\BudgetEngine;
use Capex\Domain\Envelope;
use Capex\Domain\Money;
use Capex\Domain\Verdict;

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

/** Envelope with 1,000,000.00 SGD approved, nothing committed/spent, unless overridden. */
function env(int $approved = 100_000_000, int $committed = 0, int $spent = 0): Envelope
{
    return new Envelope(1, 'MY', 2026, $approved, $committed, $spent, 1.0, 'draft');
}

// --- available() ---
check('available = approved - committed - spent',
    40_000_000, BudgetEngine::available(env(100_000_000, 35_000_000, 25_000_000)));

// --- evaluate(): within budget ---
$v = BudgetEngine::evaluate(10_000_000, env(100_000_000, 0, 0));
check('within: status', Verdict::WITHIN, $v->status);
check('within: overBy is 0', 0, $v->overBySgd);

// --- evaluate(): exactly at budget is WITHIN ---
$v = BudgetEngine::evaluate(40_000_000, env(100_000_000, 35_000_000, 25_000_000));
check('exactly-at: status', Verdict::WITHIN, $v->status);
check('exactly-at: overBy is 0', 0, $v->overBySgd);

// --- evaluate(): over budget ---
$v = BudgetEngine::evaluate(50_000_000, env(100_000_000, 35_000_000, 25_000_000));
check('over: status', Verdict::OVER, $v->status);
check('over: overBy = amount - available', 10_000_000, $v->overBySgd);

// --- Money::toSGD (FX conversion, half-up rounding) ---
check('FX: 100.00 MYR at 0.305 -> 30.50 SGD', 3050, Money::toSGD(10_000, 0.305));
check('FX: rounds half up', 3, Money::toSGD(10, 0.25)); // 2.5 -> 3

// --- authorityFor(): OVER always escalates to Group CFO ---
$bands = [5_000_000 => 'HOD', 25_000_000 => 'REGIONAL_FIN', 100_000_000 => 'COUNTRY_MD'];
$over = new Verdict(Verdict::OVER, 1);
check('authority: OVER -> GROUP_CFO regardless of amount',
    'GROUP_CFO', BudgetEngine::authorityFor(1_000_000, $over, $bands));

$within = new Verdict(Verdict::WITHIN, 0);
check('authority: small within -> HOD',
    'HOD', BudgetEngine::authorityFor(4_000_000, $within, $bands));
check('authority: above top band -> GROUP_CFO',
    'GROUP_CFO', BudgetEngine::authorityFor(200_000_000, $within, $bands));

echo "\n{$tests} checks, {$failures} failure(s)\n";
exit($failures === 0 ? 0 : 1);
