<?php
/**
 * Sales Targets — manual entry (Finance) or read-only view, per financial year.
 * @var array<int,array<string,mixed>> $targets
 * @var array<int,string> $periods   years to switch between
 * @var string $period               selected year
 * @var array<int,string> $regions   for the add-row picker
 * @var bool $canEdit
 * @var array{ok:bool,message:string}|null $flash
 * @var string $memberId
 * @var array{id:int,role:string,token:string} $user
 */
declare(strict_types=1);
$idx = capex_base() . '/index.php';
// Sensible default for the "add row" period: one past the highest year, else the selected one.
$nextPeriod = $period;
if ($periods && ctype_digit((string) $periods[0])) { $nextPeriod = (string) ((int) $periods[0] + 1); }
?>
<h1>Sales Targets</h1>
<p class="muted">
    <?php if ($canEdit): ?>
        Enter the Corp target, New target and Current met per region for the selected year. Figures are recorded as entered — nothing is calculated.
    <?php else: ?>
        Corporate sales target vs current attainment per region. Maintained by Finance.
    <?php endif; ?>
</p>

<?php if ($flash !== null): ?>
    <div class="<?= $flash['ok'] ? 'notice' : 'alert' ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="fy-bar">
    <form method="get" action="<?= e($idx) ?>" class="fy-form">
        <input type="hidden" name="screen" value="targets">
        <input type="hidden" name="member_id" value="<?= e($memberId) ?>">
        <input type="hidden" name="utok" value="<?= e($user['token']) ?>">
        <label>Financial year
            <select name="fy" onchange="this.form.submit()" class="t-in" style="width:auto;text-align:left">
                <?php if (!$periods): ?>
                    <option value="<?= e($period) ?>"><?= e($period) ?></option>
                <?php endif; ?>
                <?php foreach ($periods as $p): ?>
                    <option value="<?= e($p) ?>" <?= $p === $period ? 'selected' : '' ?>><?= e($p) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
</div>

<section class="card">
    <?php if (!$targets): ?>
        <p class="muted">No sales targets for <?= e($period) ?> yet.<?= $canEdit ? ' Add a region below.' : '' ?></p>
    <?php else: ?>
    <table class="grid">
        <thead><tr><th>Region</th><th<?= $canEdit ? '' : ' class="num"' ?>>Corp target</th><th<?= $canEdit ? '' : ' class="num"' ?>>New target</th><th<?= $canEdit ? '' : ' class="num"' ?>>Current met</th><?php if ($canEdit): ?><th></th><?php else: ?><th class="util">Attainment</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($targets as $t): ?>
            <tr>
                <td><strong><?= e($t['region']) ?></strong></td>
                <?php if ($canEdit): ?>
                    <form method="post" action="<?= e($idx) ?>?screen=targets&amp;fy=<?= rawurlencode($period) ?>" style="display:contents">
                        <input type="hidden" name="member_id" value="<?= e($memberId) ?>">
                        <input type="hidden" name="utok" value="<?= e($user['token']) ?>">
                        <input type="hidden" name="id" value="<?= e($t['id']) ?>">
                        <td><input class="t-in" name="corp_target" value="<?= e($t['corpTarget']) ?>"></td>
                        <td><input class="t-in" name="new_target" value="<?= e($t['newTarget']) ?>"></td>
                        <td><input class="t-in" name="current_met" value="<?= e($t['currentMet']) ?>"></td>
                        <td><button type="submit" class="btn-primary btn-sm">Save</button></td>
                    </form>
                <?php else: ?>
                    <td class="num"><?= money_disp($t['corpTarget']) ?></td>
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

<?php if ($canEdit): ?>
<section class="card">
    <h2>Add a region / start a new year</h2>
    <p class="muted">Create a target row for a region and year (e.g. <strong><?= e($nextPeriod) ?></strong>), then fill in its figures above.</p>
    <form method="post" action="<?= e($idx) ?>?screen=targets" class="capex-form" style="max-width:520px">
        <input type="hidden" name="member_id" value="<?= e($memberId) ?>">
        <input type="hidden" name="utok" value="<?= e($user['token']) ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <label>Region
                <select name="region" required>
                    <option value="">— pick —</option>
                    <?php foreach ($regions as $r): ?><option value="<?= e($r) ?>"><?= e($r) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Year / period
                <input type="text" name="period" value="<?= e($nextPeriod) ?>" maxlength="20" required>
            </label>
        </div>
        <div class="form-actions"><button type="submit" class="btn-primary">Add row</button></div>
    </form>
</section>
<?php endif; ?>
