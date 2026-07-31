<?php
/**
 * Sales Targets — manual entry (Finance) or read-only view.
 * @var array<int,array<string,mixed>> $targets
 * @var bool $canEdit
 * @var array{ok:bool,message:string}|null $flash
 * @var string $memberId
 * @var array{id:int,role:string,token:string} $user
 */
declare(strict_types=1);
$idx = capex_base() . '/index.php';
?>
<h1>Sales Targets</h1>
<p class="muted">
    <?php if ($canEdit): ?>
        Enter the New target and Current met per region after a capex is approved. Figures are recorded as entered — nothing is calculated.
    <?php else: ?>
        Corporate sales target vs current attainment per region. Maintained by Finance.
    <?php endif; ?>
</p>

<?php if ($flash !== null): ?>
    <div class="<?= $flash['ok'] ? 'notice' : 'alert' ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<section class="card">
    <?php if (!$targets): ?>
        <p class="muted">No sales targets yet.</p>
    <?php else: ?>
    <table class="grid">
        <thead><tr><th>Region</th><th>Period</th><th class="num">Corp target</th><th<?= $canEdit ? '' : ' class="num"' ?>>New target</th><th<?= $canEdit ? '' : ' class="num"' ?>>Current met</th><?php if ($canEdit): ?><th></th><?php else: ?><th class="util">Attainment</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($targets as $t): ?>
            <tr>
                <td><strong><?= e($t['region']) ?></strong></td>
                <td><?= e($t['period']) ?></td>
                <td class="num"><?= money_disp($t['corpTarget']) ?></td>
                <?php if ($canEdit): ?>
                    <form method="post" action="<?= e($idx) ?>?screen=targets" style="display:contents">
                        <input type="hidden" name="member_id" value="<?= e($memberId) ?>">
                        <input type="hidden" name="utok" value="<?= e($user['token']) ?>">
                        <input type="hidden" name="id" value="<?= e($t['id']) ?>">
                        <td><input class="t-in" name="new_target" value="<?= e($t['newTarget']) ?>"></td>
                        <td><input class="t-in" name="current_met" value="<?= e($t['currentMet']) ?>"></td>
                        <td><button type="submit" class="btn-primary btn-sm">Save</button></td>
                    </form>
                <?php else: ?>
                    <td class="num"><?= money_disp($t['newTarget']) ?></td>
                    <td class="num"><?= money_disp($t['currentMet']) ?></td>
                    <td class="util"><?= capex_bar($t['pct']) ?><span class="util-pct"><?= e($t['pct']) ?>%</span></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
