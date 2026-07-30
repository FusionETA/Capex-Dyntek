<?php
/**
 * New Capex Request form.
 * @var array<int,string> $regions @var array<int,string> $costCentres
 * @var array<int,string> $categories @var array<int,string> $currencies
 * @var array<string,string> $values @var array<int,string> $errors @var string $memberId
 */
declare(strict_types=1);
$v = fn (string $k): string => e($values[$k] ?? '');
$idx = capex_base() . '/index.php';
?>
<h1>New Capex Request</h1>
<p class="muted">Submit a capital expenditure request. It enters the Submitted stage and is checked against the region's budget.</p>

<?php if ($errors): ?>
    <div class="alert"><ul style="margin:0;padding-left:18px;"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<section class="card">
    <form method="post" action="<?= e($idx) ?>?screen=new" class="capex-form">
        <input type="hidden" name="member_id" value="<?= e($memberId) ?>">

        <label>Title <span class="req">*</span>
            <input type="text" name="title" value="<?= $v('title') ?>" required maxlength="255">
        </label>

        <div class="form-row">
            <label>Region <span class="req">*</span>
                <select name="region" required>
                    <option value="">—</option>
                    <?php foreach ($regions as $r): ?><option value="<?= e($r) ?>" <?= ($values['region'] ?? '') === $r ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Cost centre
                <select name="cost_centre">
                    <?php foreach ($costCentres as $c): ?><option value="<?= e($c) ?>" <?= ($values['cost_centre'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Category
                <select name="category">
                    <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>" <?= ($values['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="form-row">
            <label>Amount <span class="req">*</span>
                <input type="number" name="amount_local" value="<?= $v('amount_local') ?>" step="0.01" min="0" required>
            </label>
            <label>Currency
                <select name="currency">
                    <?php foreach ($currencies as $c): ?><option value="<?= e($c) ?>" <?= ($values['currency'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Payback (months)
                <input type="number" name="payback_months" value="<?= $v('payback_months') ?>" min="0" step="1">
            </label>
        </div>

        <label>Justification <span class="req">*</span>
            <textarea name="justification" rows="4" required><?= $v('justification') ?></textarea>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn-primary">Submit request</button>
            <a href="<?= e($idx) ?>?screen=dashboard&amp;member_id=<?= rawurlencode($memberId) ?>" class="btn-link">Cancel</a>
        </div>
    </form>
</section>
