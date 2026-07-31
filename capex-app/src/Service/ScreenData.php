<?php

declare(strict_types=1);

namespace Capex\Service;

use Capex\App;
use Capex\Domain\Money;

/**
 * Builds display-ready view models. No budget — the dashboard summarises approved
 * capex and shows sales-target progress; the targets model backs Finance's
 * manual-entry screen.
 */
final class ScreenData
{
    public function __construct(private readonly App $app)
    {
    }

    /** Dashboard: KPI summary, sales-target progress, approved-capex ranking. */
    public function dashboard(): array
    {
        $stages = $this->app->config['stages'];
        $rf = $this->app->config['fields']['request'];

        $approvedItems = $this->app->requests()->inStage($stages['approved'] ?? '');
        $pendingItems  = $this->app->requests()->inStage($stages['submitted'] ?? '');

        $approvedTotal = 0;
        $ranking = [];
        foreach ($approvedItems as $it) {
            $cents = Money::fieldToCents($it[$rf['amount_sgd']] ?? null);
            $approvedTotal += $cents;
            $ranking[] = [
                'title'   => (string) ($it['title'] ?? 'Untitled'),
                'region'  => (string) ($it[$rf['region']] ?? '—'),
                'pic'     => (string) ($it[$rf['pic']] ?? ''),
                'amountC' => $cents,
            ];
        }
        usort($ranking, fn ($a, $b) => $b['amountC'] <=> $a['amountC']);
        $ranking = array_map(
            fn ($r) => ['title' => $r['title'], 'region' => $r['region'], 'pic' => $r['pic'], 'amount' => Money::format($r['amountC'])],
            array_slice($ranking, 0, 10),
        );

        $targets = $this->targetRows();

        return [
            'approvedTotal' => Money::format($approvedTotal),
            'pendingCount'  => count($pendingItems),
            'regionsTracked' => count($targets),
            'targets'       => $targets,
            'ranking'       => $ranking,
        ];
    }

    /** Editable model for Finance's Sales Targets screen (includes record ids). */
    public function salesTargets(): array
    {
        return ['targets' => $this->targetRows(true)];
    }

    /** @return array<int,array<string,mixed>> */
    private function targetRows(bool $withId = false): array
    {
        $f = $this->app->config['fields']['target'];
        $corpTargets = $this->app->config['corp_targets'] ?? [];
        $rows = [];
        foreach ($this->app->targets()->all() as $t) {
            $region = (string) ($t[$f['region']] ?? '—');
            $corp   = (int) round((float) ($corpTargets[$region] ?? 0) * 100);
            $target = Money::fieldToCents($t[$f['target_sgd']] ?? null);
            $actual = Money::fieldToCents($t[$f['actual_sgd']] ?? null);
            $row = [
                'region'     => $region,
                'period'     => (string) ($t[$f['period']] ?? ''),
                'corpTarget' => Money::format($corp),
                'newTarget'  => Money::format($target),
                'currentMet' => Money::format($actual),
                'pct'        => $target > 0 ? (int) round($actual / $target * 100) : 0,
            ];
            if ($withId) {
                $row['id'] = (int) ($t['id'] ?? 0);
            }
            $rows[] = $row;
        }
        usort($rows, fn ($a, $b) => [$a['region'], $a['period']] <=> [$b['region'], $b['period']]);

        return $rows;
    }
}
