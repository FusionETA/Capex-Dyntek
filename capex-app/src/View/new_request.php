<?php
/**
 * New Capex Request form.
 * @var array<int,string> $regions @var array<int,string> $categories
 * @var array<int,string> $currencies @var array<string,string> $values
 * @var array<int,string> $errors @var string $memberId @var bool $canAttach
 */
declare(strict_types=1);
$v = fn (string $k): string => e($values[$k] ?? '');
$idx = capex_base() . '/index.php';
?>
<h1>New Capex Request</h1>
<p class="muted">Submit a capital expenditure request. It enters the Submitted stage and is routed to the right approver by amount. The cost centre is assigned by the approver.</p>

<?php if ($errors): ?>
    <div class="alert"><ul style="margin:0;padding-left:18px;"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<section class="card">
    <form method="post" action="<?= e($idx) ?>?screen=new" class="capex-form" enctype="multipart/form-data">
        <input type="hidden" name="member_id" value="<?= e($memberId) ?>">
        <input type="hidden" name="utok" value="<?= e($__utok ?? '') ?>">

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

        <div class="form-row">
            <label>PIC
                <input type="text" name="pic" value="<?= $v('pic') ?>" maxlength="120">
            </label>
            <label>Timeline
                <input type="text" name="timeline" value="<?= $v('timeline') ?>" placeholder="e.g. 2026/Q3" maxlength="60">
            </label>
        </div>

        <label>Justification <span class="req">*</span>
            <textarea name="justification" rows="4" required><?= $v('justification') ?></textarea>
        </label>

        <?php if ($canAttach): ?>
        <label>Attachment <span class="muted">(optional — e.g. a quote or spec)</span>
            <input type="file" name="attachment">
        </label>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn-primary">Submit request</button>
            <a href="<?= e($idx) ?>?screen=dashboard&amp;member_id=<?= rawurlencode($memberId) ?>&amp;utok=<?= rawurlencode($__utok ?? '') ?>" class="btn-link">Cancel</a>
        </div>
    </form>
</section>
