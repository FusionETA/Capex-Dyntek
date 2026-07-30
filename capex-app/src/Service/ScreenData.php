<?php

declare(strict_types=1);

namespace Capex\Service;

use Capex\App;
use Capex\Domain\BudgetEngine;
use Capex\Domain\Money;

/**
 * Turns live SPA records into display-ready view models for the three screens.
 * All money is formatted to SGD decimal strings here so the views stay dumb.
 */
final class ScreenData
{
    public function __construct(private readonly App $app)
    {
    }

    /** Dashboard: per-region rollup, sales-target progress, approved-capex ranking. */
    public function dashboard(): array
    {
        $envelopes = $this->app->envelopes()->all();

        $regions = [];
        $totApproved = $totCommitted = $totSpent = 0;
        foreach ($envelopes as $e) {
            $r = $e->region ?: '—';
            $regions[$r] ??= ['region' => $r, 'approved' => 0, 'committed' => 0, 'spent' => 0];
            $regions[$r]['approved']  += $e->approvedSgd;
            $regions[$r]['committed'] += $e->committedSgd;
            $regions[$r]['spent']     += $e->spentSgd;
            $totApproved  += $e->approvedSgd;
            $totCommitted += $e->committedSgd;
            $totSpent     += $e->spentSgd;
        }
        $regionRows = array_map(fn (array $r) => $this->regionRow($r), array_values($regions));
        usort($regionRows, fn ($a, $b) => strcmp($a['region'], $b['region']));

        return [
            'regions' => $regionRows,
            'totals'  => [
                'approved'  => Money::format($totApproved),
                'committed' => Money::format($totCommitted),
                'spent'     => Money::format($totSpent),
                'available' => Money::format($totApproved - $totCommitted - $totSpent),
                'utilPct'   => $this->pct($totCommitted + $totSpent, $totApproved),
            ],
            'targets' => $this->targetRows(),
            'ranking' => $this->approvedRanking(),
        ];
    }

    /** Budget: one row per envelope, with availability + over-budget flag. */
    public function budget(): array
    {
        $rows = [];
        foreach ($this->app->envelopes()->all() as $e) {
            $available = BudgetEngine::available($e);
            $rows[] = [
                'region'    => $e->region ?: '—',
                'fy'        => $e->fy,
                'approved'  => Money::format($e->approvedSgd),
                'committed' => Money::format($e->committedSgd),
                'spent'     => Money::format($e->spentSgd),
                'available' => Money::format($available),
                'over'      => $available < 0,
                'utilPct'   => $this->pct($e->committedSgd + $e->spentSgd, $e->approvedSgd),
                'status'    => $e->status,
            ];
        }
        usort($rows, fn ($a, $b) => [$a['region'], $a['fy']] <=> [$b['region'], $b['fy']]);

        return ['envelopes' => $rows];
    }

    /** Targets: sales target vs actual per region/period. */
    public function targets(): array
    {
        return ['targets' => $this->targetRows()];
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function regionRow(array $r): array
    {
        $used = $r['committed'] + $r['spent'];
        return [
            'region'    => $r['region'],
            'approved'  => Money::format($r['approved']),
            'committed' => Money::format($r['committed']),
            'spent'     => Money::format($r['spent']),
            'available' => Money::format($r['approved'] - $used),
            'utilPct'   => $this->pct($used, $r['approved']),
            'over'      => $used > $r['approved'],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function targetRows(): array
    {
        $f = $this->app->config['fields']['target'];
        $rows = [];
        foreach ($this->app->targets()->all() as $t) {
            $target = Money::fieldToCents($t[$f['target_sgd']] ?? null);
            $actual = Money::fieldToCents($t[$f['actual_sgd']] ?? null);
            $rows[] = [
                'region' => (string) ($t[$f['region']] ?? '—'),
                'period' => (string) ($t[$f['period']] ?? ''),
                'target' => Money::format($target),
                'actual' => Money::format($actual),
                'pct'    => $this->pct($actual, $target),
            ];
        }
        usort($rows, fn ($a, $b) => [$a['region'], $a['period']] <=> [$b['region'], $b['period']]);

        return $rows;
    }

    /** Top approved requests by SGD amount. @return array<int,array<string,mixed>> */
    private function approvedRanking(int $limit = 10): array
    {
        $f = $this->app->config['fields']['request'];
        $approvedStage = $this->app->config['stages']['approved'] ?? '';

        $rows = [];
        foreach ($this->app->requests()->inStage($approvedStage) as $item) {
            $rows[] = [
                'title'   => (string) ($item['title'] ?? $item['name'] ?? 'Untitled'),
                'region'  => (string) ($item[$f['region']] ?? '—'),
                'amountC' => Money::fieldToCents($item[$f['amount_sgd']] ?? null),
            ];
        }
        usort($rows, fn ($a, $b) => $b['amountC'] <=> $a['amountC']);

        return array_map(
            fn (array $r) => ['title' => $r['title'], 'region' => $r['region'], 'amount' => Money::format($r['amountC'])],
            array_slice($rows, 0, $limit),
        );
    }

    private function pct(int $part, int $whole): int
    {
        return $whole > 0 ? (int) round($part / $whole * 100) : 0;
    }
}
