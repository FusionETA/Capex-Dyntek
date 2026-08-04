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

    /**
     * Dashboard, scoped to one financial year (calendar year). Approved-capex
     * figures count in the FY they were APPROVED in (date_approval, falling back to
     * the record's created date for older rows). "Pending" stays live — it's the
     * current queue, not a period figure.
     */
    public function dashboard(?string $period = null): array
    {
        $stages = $this->app->config['stages'];
        $rf = $this->app->config['fields']['request'];

        $approvedItems = $this->app->requests()->inStage($stages['approved'] ?? '');
        $pendingItems  = $this->app->requests()->inStage($stages['submitted'] ?? '');

        // Which years to offer: any year we have approved capex or a sales target for.
        $years = [];
        foreach ($approvedItems as $it) {
            if (($y = $this->requestYear($it)) !== '') {
                $years[$y] = true;
            }
        }
        foreach ($this->periods() as $p) {
            $years[$p] = true;
        }
        $fyDefault = (string) ($this->app->config['current_fy'] ?? '');
        if ($fyDefault !== '') {
            $years[$fyDefault] = true;
        }
        $periods = array_map('strval', array_keys($years));
        rsort($periods);
        $fy = $period !== null && $period !== '' ? $period
            : (in_array($fyDefault, $periods, true) ? $fyDefault : ($periods[0] ?? $fyDefault));

        // Approved capex within the selected FY.
        $approvedTotal = 0;
        $byRegion = [];
        $byCategory = [];
        $monthly = array_fill(1, 12, 0);
        $ranking = [];
        $daySum = 0;
        $dayN = 0;
        foreach ($approvedItems as $it) {
            if ($this->requestYear($it) !== $fy) {
                continue;
            }
            $cents = Money::fieldToCents($it[$rf['amount_sgd']] ?? null);
            $approvedTotal += $cents;

            $region = (string) ($it[$rf['region']] ?? '—');
            $category = (string) ($it[$rf['category']] ?? '—');
            $byRegion[$region] = ($byRegion[$region] ?? 0) + $cents;
            $byCategory[$category !== '' ? $category : '—'] = ($byCategory[$category !== '' ? $category : '—'] ?? 0) + $cents;

            $month = (int) substr($this->requestDate($it), 5, 2);
            if ($month >= 1 && $month <= 12) {
                $monthly[$month] += $cents;
            }

            $days = $this->daysToApprove($it);
            if ($days !== null) {
                $daySum += $days;
                $dayN++;
            }

            $ranking[] = [
                'title'   => (string) ($it['title'] ?? 'Untitled'),
                'region'  => $region,
                'pic'     => (string) ($it[$rf['pic']] ?? ''),
                'amountC' => $cents,
            ];
        }

        usort($ranking, fn ($a, $b) => $b['amountC'] <=> $a['amountC']);
        $approvedCount = count($ranking);
        $ranking = array_map(
            fn ($r) => ['title' => $r['title'], 'region' => $r['region'], 'pic' => $r['pic'], 'amount' => Money::format($r['amountC'])],
            array_slice($ranking, 0, 10),
        );

        $targets = $this->targetRows(false, $fy);

        return [
            'fy'            => $fy,
            'periods'       => $periods,
            'approvedTotal' => Money::format($approvedTotal),
            'approvedCount' => $approvedCount,
            'pendingCount'  => count($pendingItems),   // live, not FY-scoped
            'avgDays'       => $dayN > 0 ? (int) round($daySum / $dayN) : null,
            'regionsTracked' => count($targets),
            'byRegion'      => $this->breakdown($byRegion),
            'byCategory'    => $this->breakdown($byCategory),
            'monthly'       => $this->monthlySeries($monthly),
            'targets'       => $targets,
            'ranking'       => $ranking,
        ];
    }

    /**
     * Turn a label => cents map into sorted display rows with a bar percentage
     * relative to the largest value.
     * @param array<string,int> $map
     * @return array<int,array{label:string,amount:string,pct:int}>
     */
    private function breakdown(array $map): array
    {
        arsort($map);
        $max = $map ? max($map) : 0;
        $rows = [];
        foreach ($map as $label => $cents) {
            $rows[] = [
                'label'  => (string) $label,
                'amount' => Money::format($cents),
                'pct'    => $max > 0 ? (int) round($cents / $max * 100) : 0,
            ];
        }

        return $rows;
    }

    /**
     * Monthly series for the mini bar chart: 12 entries with a height percentage
     * relative to the busiest month.
     * @param array<int,int> $monthly month(1-12) => cents
     * @return array<int,array{label:string,amount:string,pct:int}>
     */
    private function monthlySeries(array $monthly): array
    {
        $labels = ['', 'J', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O', 'N', 'D'];
        $max = $monthly ? max($monthly) : 0;
        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $out[] = [
                'label'  => $labels[$m],
                'amount' => Money::format($monthly[$m]),
                'pct'    => $max > 0 ? (int) round($monthly[$m] / $max * 100) : 0,
            ];
        }

        return $out;
    }

    /** The date that places a request in a period: approval date, else created date. */
    private function requestDate(array $item): string
    {
        $rf = $this->app->config['fields']['request'];
        $appr = (string) ($item[$rf['date_approval'] ?? ''] ?? '');
        if ($appr !== '') {
            return $appr;
        }

        return (string) ($item['createdTime'] ?? '');
    }

    /** Calendar-year (FY) of a request, from its placing date. '' if unknown. */
    private function requestYear(array $item): string
    {
        return substr($this->requestDate($item), 0, 4);
    }

    /** Days between request and approval, or null if either date is missing. */
    private function daysToApprove(array $item): ?int
    {
        $rf = $this->app->config['fields']['request'];
        $req = strtotime((string) ($item[$rf['date_request'] ?? ''] ?? ''));
        $appr = strtotime((string) ($item[$rf['date_approval'] ?? ''] ?? ''));
        if ($req === false || $appr === false || $appr < $req) {
            return null;
        }

        return (int) floor(($appr - $req) / 86400);
    }

    /**
     * Editable model for Finance's Sales Targets screen (includes record ids),
     * filtered to one financial year, plus the list of years to switch between.
     */
    public function salesTargets(?string $period = null): array
    {
        $periods = $this->periods();
        $fy = (string) ($this->app->config['current_fy'] ?? '');
        $selected = $period !== null && $period !== '' ? $period
            : (in_array($fy, $periods, true) ? $fy : ($periods[0] ?? $fy));

        return [
            'targets'  => $this->targetRows(true, $selected),
            'periods'  => $periods,
            'period'   => $selected,
        ];
    }

    /** Distinct periods present on target records, newest first. @return array<int,string> */
    public function periods(): array
    {
        $f = $this->app->config['fields']['target'];
        $seen = [];
        foreach ($this->app->targets()->all() as $t) {
            $p = (string) ($t[$f['period']] ?? '');
            if ($p !== '') {
                $seen[$p] = true;
            }
        }
        // array_keys casts numeric strings ("2026") to int — force back to string.
        $periods = array_map('strval', array_keys($seen));
        rsort($periods);

        return $periods;
    }

    /**
     * @param bool $withId include the Bitrix record id (for editing)
     * @param string|null $period only rows in this period; null = all periods
     * @return array<int,array<string,mixed>>
     */
    private function targetRows(bool $withId = false, ?string $period = null): array
    {
        $f = $this->app->config['fields']['target'];
        $corpTargets = $this->app->config['corp_targets'] ?? [];
        $corpCode = (string) ($f['corp_target'] ?? '');
        $rows = [];
        foreach ($this->app->targets()->all() as $t) {
            $rowPeriod = (string) ($t[$f['period']] ?? '');
            if ($period !== null && $period !== '' && $rowPeriod !== $period) {
                continue;
            }
            $region = (string) ($t[$f['region']] ?? '—');
            // Corp target: the record's own value wins; fall back to config for records
            // created before the field existed (keeps existing years working).
            $corp = $corpCode !== '' ? Money::fieldToCents($t[$corpCode] ?? null) : 0;
            if ($corp <= 0) {
                $corp = (int) round((float) ($corpTargets[$region] ?? 0) * 100);
            }
            $target = Money::fieldToCents($t[$f['target_sgd']] ?? null);
            $actual = Money::fieldToCents($t[$f['actual_sgd']] ?? null);
            $row = [
                'region'     => $region,
                'period'     => $rowPeriod,
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
