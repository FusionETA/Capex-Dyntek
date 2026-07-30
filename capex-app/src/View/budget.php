<?php
/**
 * Budget view. Model: $envelopes.
 * @var array<int,array<string,mixed>> $envelopes
 */
declare(strict_types=1);
$overCount = count(array_filter($envelopes, fn ($e) => $e['over']));
?>
<h1>Budget</h1>
<p class="muted">Envelope vs committed vs spent, per region and fiscal year. Finance/CFO edit envelopes in Bitrix24; this view is read-only.</p>

<?php if ($overCount): ?>
    <div class="alert"><?= e($overCount) ?> envelope<?= $overCount > 1 ? 's are' : ' is' ?> over budget.</div>
<?php endif; ?>

<section class="card">
    <?php if (!$envelopes): ?>
        <p class="muted">No budget envelopes yet. Create one in the Budget Envelope Smart Process.</p>
    <?php else: ?>
    <table class="grid">
        <thead><tr><th>Region</th><th>FY</th><th class="num">Approved</th><th class="num">Committed</th><th class="num">Spent</th><th class="num">Available</th><th class="util">Utilisation</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($envelopes as $e): ?>
            <tr class="<?= $e['over'] ? 'row-over' : '' ?>">
                <td><strong><?= e($e['region']) ?></strong></td>
                <td><?= e($e['fy']) ?></td>
                <td class="num"><?= money_disp($e['approved']) ?></td>
                <td class="num"><?= money_disp($e['committed']) ?></td>
                <td class="num"><?= money_disp($e['spent']) ?></td>
                <td class="num <?= $e['over'] ? 'verdict-over' : '' ?>"><?= money_disp($e['available']) ?></td>
                <td class="util"><?= capex_bar($e['utilPct'], $e['over']) ?><span class="util-pct"><?= e($e['utilPct']) ?>%</span></td>
                <td><span class="chip"><?= e($e['status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
