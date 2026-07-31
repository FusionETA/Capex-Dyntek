<?php
/**
 * Dashboard view. Model: $approvedTotal, $pendingCount, $regionsTracked, $targets, $ranking.
 * @var string $approvedTotal @var int $pendingCount @var int $regionsTracked
 * @var array<int,array<string,mixed>> $targets
 * @var array<int,array<string,mixed>> $ranking
 */
declare(strict_types=1);
?>
<h1>Dashboard</h1>

<section class="kpis">
    <div class="kpi"><span class="kpi-label">Approved capex</span><span class="kpi-val">S$ <?= money_disp($approvedTotal) ?></span></div>
    <div class="kpi"><span class="kpi-label">Pending approval</span><span class="kpi-val"><?= e($pendingCount) ?></span></div>
    <div class="kpi"><span class="kpi-label">Regions tracked</span><span class="kpi-val"><?= e($regionsTracked) ?></span></div>
</section>

<section class="card">
    <h2>Sales targets</h2>
    <?php if (!$targets): ?>
        <p class="muted">No sales targets set. Finance can add them on the Sales Targets screen.</p>
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
    <h2>Top approved capex</h2>
    <?php if (!$ranking): ?>
        <p class="muted">No approved requests yet.</p>
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
