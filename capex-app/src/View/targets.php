<?php
/**
 * Targets view. Model: $targets.
 * @var array<int,array<string,mixed>> $targets
 */
declare(strict_types=1);
?>
<h1>Sales targets</h1>
<p class="muted">Maintained by Finance in Bitrix24; read-only here.</p>

<section class="card">
    <?php if (!$targets): ?>
        <p class="muted">No sales targets set. Create them in the Sales Target Smart Process.</p>
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
