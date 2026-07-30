<?php
/**
 * Dashboard view. Model: $regions, $totals, $targets, $ranking.
 * @var array<string,mixed> $totals
 * @var array<int,array<string,mixed>> $regions
 * @var array<int,array<string,mixed>> $targets
 * @var array<int,array<string,mixed>> $ranking
 */
declare(strict_types=1);
?>
<h1>Dashboard</h1>

<section class="kpis">
    <div class="kpi"><span class="kpi-label">Approved budget</span><span class="kpi-val">S$ <?= money_disp($totals['approved']) ?></span></div>
    <div class="kpi"><span class="kpi-label">Committed</span><span class="kpi-val">S$ <?= money_disp($totals['committed']) ?></span></div>
    <div class="kpi"><span class="kpi-label">Spent</span><span class="kpi-val">S$ <?= money_disp($totals['spent']) ?></span></div>
    <div class="kpi"><span class="kpi-label">Available</span><span class="kpi-val">S$ <?= money_disp($totals['available']) ?></span></div>
</section>

<section class="card">
    <h2>By region</h2>
    <?php if (!$regions): ?>
        <p class="muted">No budget envelopes yet.</p>
    <?php else: ?>
    <table class="grid">
        <thead><tr><th>Region</th><th class="num">Approved</th><th class="num">Committed</th><th class="num">Spent</th><th class="num">Available</th><th class="util">Utilisation</th></tr></thead>
        <tbody>
        <?php foreach ($regions as $r): ?>
            <tr>
                <td><strong><?= e($r['region']) ?></strong></td>
                <td class="num"><?= money_disp($r['approved']) ?></td>
                <td class="num"><?= money_disp($r['committed']) ?></td>
                <td class="num"><?= money_disp($r['spent']) ?></td>
                <td class="num <?= $r['over'] ? 'verdict-over' : '' ?>"><?= money_disp($r['available']) ?></td>
                <td class="util"><?= capex_bar($r['utilPct'], $r['over']) ?><span class="util-pct"><?= e($r['utilPct']) ?>%</span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<div class="two-col">
    <section class="card">
        <h2>Sales targets</h2>
        <?php if (!$targets): ?>
            <p class="muted">No sales targets set.</p>
        <?php else: ?>
        <table class="grid">
            <thead><tr><th>Region</th><th>Period</th><th class="num">Target</th><th class="num">Actual</th><th class="util">Progress</th></tr></thead>
            <tbody>
            <?php foreach ($targets as $t): ?>
                <tr>
                    <td><strong><?= e($t['region']) ?></strong></td>
                    <td><?= e($t['period']) ?></td>
                    <td class="num"><?= money_disp($t['target']) ?></td>
                    <td class="num"><?= money_disp($t['actual']) ?></td>
                    <td class="util"><?= capex_bar($t['pct']) ?><span class="util-pct"><?= e($t['pct']) ?>%</span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Top approved capex</h2>
        <?php if (!$ranking): ?>
            <p class="muted">No approved requests yet.</p>
        <?php else: ?>
        <ol class="ranking">
            <?php foreach ($ranking as $r): ?>
                <li><span class="rank-title"><?= e($r['title']) ?> <span class="chip"><?= e($r['region']) ?></span></span><span class="rank-amt">S$ <?= money_disp($r['amount']) ?></span></li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>
    </section>
</div>
