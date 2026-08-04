<?php
/**
 * History detail — full request, event timeline, and (for editors) an edit form.
 * @var array<string,mixed> $r presented request (+ events, options)
 * @var bool $canEdit
 * @var array{ok:bool,message:string}|null $flash
 * @var string $memberId
 * @var array{id:int,role:string,token:string} $user
 */
declare(strict_types=1);
$idx = capex_base() . '/index.php';
$mq = ($memberId !== '' ? '&amp;member_id=' . rawurlencode($memberId) : '')
    . ($user['token'] !== '' ? '&amp;utok=' . rawurlencode($user['token']) : '');
$opt = $r['options'];
$sel = static fn (string $cur, string $v): string => $cur === $v ? 'selected' : '';
$eventLabel = ['submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected', 'edited' => 'Edited'];
?>
<p><a href="<?= e($idx) ?>?screen=history<?= $mq ?>" class="btn-link">← Back to history</a></p>
<h1><?= e($r['title']) ?> <span class="chip <?= $r['stage'] === 'Approved' ? 'chip-ok' : ($r['stage'] === 'Rejected' ? 'chip-no' : '') ?>"><?= e($r['stage']) ?></span></h1>

<?php if ($flash !== null): ?>
    <div class="<?= $flash['ok'] ? 'notice' : 'alert' ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<section class="card">
    <h3>Timeline</h3>
    <?php if (!$r['events']): ?>
        <p class="muted">No recorded events.</p>
    <?php else: ?>
    <ul class="timeline">
        <?php foreach ($r['events'] as $ev): ?>
            <li>
                <span class="tl-type tl-<?= e($ev['type'] ?? '') ?>"><?= e($eventLabel[$ev['type'] ?? ''] ?? ($ev['type'] ?? '')) ?></span>
                <span class="tl-when"><?= e(str_replace('T', ' ', substr((string) ($ev['ts'] ?? ''), 0, 16))) ?></span>
                <span class="tl-who">
                    <?= e((string) ($ev['byName'] ?? 'System')) ?><?php if (($ev['byRoleLabel'] ?? '') !== ''): ?> <span class="muted">(<?= e($ev['byRoleLabel']) ?>)</span><?php endif; ?>
                </span>
                <?php if (($ev['costCentre'] ?? '') !== ''): ?><div class="tl-note">Cost centre → <strong><?= e($ev['costCentre']) ?></strong></div><?php endif; ?>
                <?php if (($ev['note'] ?? '') !== ''): ?><div class="tl-note">“<?= e($ev['note']) ?>”</div><?php endif; ?>
                <?php if (!empty($ev['changes'])): ?>
                    <div class="tl-note">
                        <?php foreach ($ev['changes'] as $ch): ?>
                            <div><strong><?= e($ch['field'] ?? '') ?>:</strong> <span class="muted"><?= e($ch['from'] ?? '') ?></span> → <?= e($ch['to'] ?? '') ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>

<?php if ($r['attachment'] !== null && $r['attachment']['href'] !== ''): ?>
<section class="card"><h3>Attachment</h3><p>📎 <a href="<?= e($r['attachment']['href']) ?>" target="_blank" rel="noopener"><?= e($r['attachment']['name']) ?></a></p></section>
<?php endif; ?>

<?php if ($canEdit): ?>
<section class="card">
    <h3>Edit request</h3>
    <p class="muted">Changes update the Bitrix record and are logged to the timeline above.</p>
    <form method="post" action="<?= e($idx) ?>?screen=history" class="capex-form">
        <input type="hidden" name="member_id" value="<?= e($memberId) ?>">
        <input type="hidden" name="utok" value="<?= e($user['token']) ?>">
        <input type="hidden" name="id" value="<?= e($r['id']) ?>">

        <label>Title <input type="text" name="title" value="<?= e($r['title']) ?>" maxlength="255"></label>

        <div class="form-row">
            <label>Region
                <select name="region">
                    <?php foreach ($opt['regions'] as $o): ?><option value="<?= e($o) ?>" <?= $sel($r['region'], $o) ?>><?= e($o) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Cost centre
                <select name="cost_centre">
                    <option value="">—</option>
                    <?php foreach ($opt['costCentres'] as $o): ?><option value="<?= e($o) ?>" <?= $sel($r['costCentre'], $o) ?>><?= e($o) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Category
                <select name="category">
                    <option value="">—</option>
                    <?php foreach ($opt['categories'] as $o): ?><option value="<?= e($o) ?>" <?= $sel($r['category'], $o) ?>><?= e($o) ?></option><?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="form-row">
            <label>Amount (local) <input type="number" name="amount_local" value="<?= e($r['amountLocal']) ?>" step="0.01" min="0"></label>
            <label>Currency
                <select name="currency">
                    <?php foreach ($opt['currencies'] as $o): ?><option value="<?= e($o) ?>" <?= $sel($r['currency'], $o) ?>><?= e($o) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Payback (months) <input type="number" name="payback_months" value="<?= e($r['payback']) ?>" min="0" step="1"></label>
        </div>

        <label>Justification <textarea name="justification" rows="3"><?= e($r['justification']) ?></textarea></label>
        <label>Approval note <textarea name="approval_note" rows="2"><?= e($r['approvalNote']) ?></textarea></label>

        <div class="form-actions">
            <button type="submit" class="btn-primary">Save changes</button>
            <a href="<?= e($idx) ?>?screen=history<?= $mq ?>" class="btn-link">Cancel</a>
        </div>
    </form>
</section>
<?php endif; ?>
