<?php
/**
 * Dashboard view — analytic, scoped to one financial year.
 * @var string $fy @var array<int,string> $periods
 * @var string $approvedTotal @var int $approvedCount @var int $pendingCount @var int|null $avgDays
 * @var int $regionsTracked
 * @var array<int,array{label:string,amount:string,pct:int}> $byRegion
 * @var array<int,array{label:string,amount:string,pct:int}> $byCategory
 * @var array<int,array{label:string,amount:string,pct:int}> $monthly
 * @var array<int,array<string,mixed>> $targets
 * @var array<int,array<string,mixed>> $ranking
 * @var string $memberId @var array{id:int,role:string,token:string} $user
 */
declare(strict_types=1);
$idx = capex_base() . '/index.php';
$bars = function (array $rows): string {
    if (!$rows) { return '<p class="muted">No approved capex in this year.</p>'; }
    $h = '<ul class="breakdown">';
    foreach ($rows as $r) {
        $h .= '<li><span class="bd-label">' . e($r['label']) . '</span>'
            . '<span class="bar"><span class="bar-fill" style="width:' . (int) $r['pct'] . '%"></span></span>'
            . '<span class="bd-amt">S$ ' . money_disp($r['amount']) . '</span></li>';
    }
    return $h . '</ul>';
};
?>
<h1>Dashboard</h1>

<div class="fy-bar">
    <form method="get" action="<?= e($idx) ?>" class="fy-form">
        <input type="hidden" name="screen" value="dashboard">
        <input type="hidden" name="member_id" value="<?= e($memberId) ?>">
        <input type="hidden" name="utok" value="<?= e($user['token']) ?>">
        <label>Financial year
            <select name="fy" onchange="this.form.submit()" class="t-in" style="width:auto;text-align:left">
                <?php foreach ($periods as $p): ?>
                    <option value="<?= e($p) ?>" <?= $p === $fy ? 'selected' : '' ?>><?= e($p) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
</div>

<section class="kpis">
    <div class="kpi"><span class="kpi-label">Approved capex · FY<?= e($fy) ?></span><span class="kpi-val">S$ <?= money_disp($approvedTotal) ?></span></div>
    <div class="kpi"><span class="kpi-label">Approved (count)</span><span class="kpi-val"><?= e($approvedCount) ?></span></div>
    <div class="kpi"><span class="kpi-label">Pending approval · now</span><span class="kpi-val"><?= e($pendingCount) ?></span></div>
    <div class="kpi"><span class="kpi-label">Avg days to approve</span><span class="kpi-val"><?= $avgDays === null ? '—' : e($avgDays) ?></span></div>
</section>

<div class="two-col">
    <section class="card">
        <h2>Approved capex by region</h2>
        <?= $bars($byRegion) ?>
    </section>
    <section class="card">
        <h2>Approved capex by category</h2>
        <?= $bars($byCategory) ?>
    </section>
</div>

<section class="card">
    <h2>Approved capex by month · FY<?= e($fy) ?></h2>
    <?php $hasMonthly = array_filter($monthly, fn ($m) => $m['pct'] > 0); ?>
    <?php if (!$hasMonthly): ?>
        <p class="muted">No approved capex in this year.</p>
    <?php else: ?>
    <div class="spark">
        <?php foreach ($monthly as $m): ?>
            <div class="spark-col" title="S$ <?= money_disp($m['amount']) ?>">
                <span class="spark-track"><span class="spark-bar" style="height:<?= (int) $m['pct'] ?>%"></span></span>
                <span class="spark-lbl"><?= e($m['label']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Sales targets · FY<?= e($fy) ?></h2>
    <?php if (!$targets): ?>
        <p class="muted">No sales targets for this year. Finance can add them on the Sales Targets screen.</p>
    <?php else: ?>
    <table class="grid">
        <thead><tr><th>Region</th><th class="num">Corp target</th><th class="num">New target</th><th class="num">Current met</th><th class="util">Attainment</th></tr></thead>
        <tbody>
        <?php foreach ($targets as $t): ?>
            <tr>
                <td><strong><?= e($t['region']) ?></strong></td>
                <td class="num"><?= money_disp($t['corpTarget']) ?></td>
                <td class="num"><?= money_disp($t['newTarget']) ?></td>
                <td class="num"><?= money_disp($t['currentMet']) ?></td>
                <td class="util"><?= capex_bar($t['pct']) ?><span class="util-pct"><?= e($t['pct']) ?>%</span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Top approved capex · FY<?= e($fy) ?></h2>
    <?php if (!$ranking): ?>
        <p class="muted">No approved requests in this year.</p>
    <?php else: ?>
    <table class="grid">
        <thead><tr><th>#</th><th>Subject</th><th>Region</th><th>PIC</th><th class="num">Value (SGD)</th></tr></thead>
        <tbody>
        <?php foreach ($ranking as $i => $r): ?>
            <tr>
                <td><?= e($i + 1) ?></td>
                <td><?= e($r['title']) ?></td>
                <td><strong><?= e($r['region']) ?></strong></td>
                <td><?= e($r['pic']) ?></td>
                <td class="num"><?= money_disp($r['amount']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
