<?php

declare(strict_types=1);

/**
 * Exercises the full webhook re-sum path — Requests + Envelopes repos through
 * Service\Recalculator — against a fake Bitrix client, no network. Proves:
 *   - committed/spent are derived from live records and written back in decimal
 *   - a replayed webhook lands on identical totals (idempotent)
 *   - a rejection (request leaves the Approved stage) releases its commitment
 *   - money crosses the Bitrix boundary as decimals but is summed in cents
 *
 * Run: php capex-app/tests/RecalculatorTest.php
 */

require __DIR__ . '/../src/Autoload.php';

use Capex\Bitrix\ClientInterface;
use Capex\Domain\Envelope;
use Capex\Repo\Envelopes;
use Capex\Repo\Requests;
use Capex\Service\Recalculator;

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

/**
 * Fake Bitrix client. crm.item.list returns canned request rows per stage;
 * crm.item.update is captured so the test can assert what was written back.
 * Bitrix money fields are decimals ("1234.56"), matching the real API.
 */
final class FakeClient implements ClientInterface
{
    /** @var array<string,array<int,array<string,string>>> stageId => rows */
    public array $byStage = [];

    /** @var array<int,array<string,mixed>> captured crm.item.update payloads */
    public array $updates = [];

    public function call(string $method, array $params = []): array
    {
        if ($method === 'crm.item.list') {
            $stage = (string) ($params['filter']['stageId'] ?? '');
            return ['result' => ['items' => $this->byStage[$stage] ?? []]];
        }

        if ($method === 'crm.item.update') {
            $this->updates[] = $params;
            return ['result' => ['item' => ['id' => $params['id']]]];
        }

        return ['result' => []];
    }

    public function batch(array $commands, bool $halt = false): array
    {
        return [];
    }
}

// --- config-ish plumbing ---
$requestFields = [
    'amount_sgd'  => 'ufCrm_AMOUNT_SGD',
    'envelope_id' => 'ufCrm_ENVELOPE_ID',
];
$envelopeFields = [
    'region'        => 'ufCrm_REGION',
    'fy'           => 'ufCrm_FY',
    'approved_sgd'  => 'ufCrm_APPROVED_SGD',
    'committed_sgd' => 'ufCrm_COMMITTED_SGD',
    'spent_sgd'     => 'ufCrm_SPENT_SGD',
    'fx_rate_to_sgd'=> 'ufCrm_FX_RATE_TO_SGD',
    'status'        => 'ufCrm_STATUS',
];
$stages = ['approved' => 'DT_1:SUCCESS', 'closed' => 'DT_1:UC_CLOSED'];

$envelope = new Envelope(7, 'MY', 2026, 100_000_000, 0, 0, 1.0, 'draft');

$fake = new FakeClient();
$requests = new Requests($fake, 180, $requestFields);
$envelopes = new Envelopes($fake, 181, $envelopeFields);
$recalc = new Recalculator($requests, $envelopes, $stages);

// Two approved requests (300k + 200k SGD), one closed (100k SGD) — all decimals.
$fake->byStage['DT_1:SUCCESS'] = [
    ['id' => 1, 'ufCrm_AMOUNT_SGD' => '300000.00'],
    ['id' => 2, 'ufCrm_AMOUNT_SGD' => '200000.00'],
];
$fake->byStage['DT_1:UC_CLOSED'] = [
    ['id' => 3, 'ufCrm_AMOUNT_SGD' => '100000.00'],
];

// --- first recalc ---
$totals = $recalc->recalc($envelope);
check('committed derived in cents', 50_000_000, $totals['committed']);
check('spent derived in cents', 10_000_000, $totals['spent']);

$lastUpdate = end($fake->updates);
check('writeTotals hits the envelope id', 7, $lastUpdate['id']);
check('committed written back as decimal', '500000.00', $lastUpdate['fields']['ufCrm_COMMITTED_SGD']);
check('spent written back as decimal', '100000.00', $lastUpdate['fields']['ufCrm_SPENT_SGD']);

// --- replayed webhook: identical inputs -> identical totals (idempotent) ---
$replay = $recalc->recalc($envelope);
check('replay lands on identical totals', $totals, $replay);

// --- rejection: request 1 (300k) leaves the Approved stage -> commitment released ---
$fake->byStage['DT_1:SUCCESS'] = [
    ['id' => 2, 'ufCrm_AMOUNT_SGD' => '200000.00'],
];
$afterReject = $recalc->recalc($envelope);
check('rejection releases the 300k commitment', 20_000_000, $afterReject['committed']);
check('rejection leaves spent untouched', 10_000_000, $afterReject['spent']);

// --- envelope hydration parses decimals into cents (no lost precision) ---
$getClient = new class implements ClientInterface {
    public function call(string $method, array $params = []): array
    {
        if ($method === 'crm.item.get') {
            return ['result' => ['item' => [
                'id' => 7,
                'ufCrm_REGION' => 'MY',
                'ufCrm_FY' => 2026,
                'ufCrm_APPROVED_SGD' => '1000000.55',
                'ufCrm_COMMITTED_SGD' => '0.00',
                'ufCrm_SPENT_SGD' => '0.00',
                'ufCrm_FX_RATE_TO_SGD' => '1.0',
                'ufCrm_STATUS' => 'draft',
            ]]];
        }
        return ['result' => []];
    }

    public function batch(array $commands, bool $halt = false): array
    {
        return [];
    }
};
$env2 = (new Envelopes($getClient, 181, $envelopeFields))->getById(7);
check('approved hydrated to exact cents', 100_000_055, $env2?->approvedSgd);

echo "\n{$tests} checks, {$failures} failure(s)\n";
exit($failures === 0 ? 0 : 1);
