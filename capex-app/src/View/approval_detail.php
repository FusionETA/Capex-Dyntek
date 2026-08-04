<?php
/**
 * Approval detail — full request, plus approve/reject with cost centre + note.
 * @var array<string,mixed> $r presented request (+ required, canDecide, awaiting)
 * @var array<int,string> $costCentres
 * @var array{ok:bool,message:string}|null $flash
 * @var string $memberId
 * @var array{id:int,role:string,token:string} $user
 */
declare(strict_types=1);
$idx = capex_base() . '/index.php';
$mq = ($memberId !== '' ? '&amp;member_id=' . rawurlencode($memberId) : '')
    . ($user['token'] !== '' ? '&amp;utok=' . rawurlencode($user['token']) : '');
$roleLabel = [
    'REQUESTER' => 'Requester', 'HOD' => 'HOD', 'REGIONAL_FIN' => 'Regional Finance',
    'COUNTRY_MD' => 'Country MD', 'GROUP_CFO' => 'Group CFO', 'SYSTEM_ADMIN' => 'System Admin',
];
?>
<p><a href="<?= e($idx) ?>?screen=approvals<?= $mq ?>" class="btn-link">← Back to approvals</a></p>
<h1><?= e($r['title']) ?> <span class="chip"><?= e($r['stage']) ?></span></h1>

<?php if ($flash !== null): ?>
    <div class="<?= $flash['ok'] ? 'notice' : 'alert' ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<section class="card">
    <dl class="detail-grid">
        <dt>Request #</dt><dd><?= e($r['id']) ?></dd>
        <dt>Region</dt><dd><strong><?= e($r['region']) ?></strong></dd>
        <dt>Amount</dt><dd><?= e($r['currency']) ?> <?= money_disp($r['amountLocal']) ?> &nbsp;<span class="muted">(S$ <?= money_disp($r['amountSgd']) ?>)</span></dd>
        <dt>Category</dt><dd><?= e($r['category'] !== '' ? $r['category'] : '—') ?></dd>
        <dt>Cost centre</dt><dd><?= e($r['costCentre'] !== '' ? $r['costCentre'] : 'Not set — choose on approval') ?></dd>
        <dt>PIC</dt><dd><?= e($r['pic'] !== '' ? $r['pic'] : '—') ?></dd>
        <dt>Timeline</dt><dd><?= e($r['timeline'] !== '' ? $r['timeline'] : '—') ?></dd>
        <dt>Payback</dt><dd><?= e($r['payback'] !== '' ? $r['payback'] . ' months' : '—') ?></dd>
        <dt>Submitted</dt><dd><?= e($r['dateRequest'] !== '' ? $r['dateRequest'] : '—') ?></dd>
        <dt>Required approver</dt><dd><span class="chip"><?= e($roleLabel[$r['required']] ?? $r['required']) ?></span></dd>
    </dl>

    <h3>Justification</h3>
    <p class="justification"><?= nl2br(e($r['justification'] !== '' ? $r['justification'] : '—')) ?></p>

    <h3>Attachment</h3>
    <?php if ($r['attachment'] !== null && $r['attachment']['href'] !== ''): ?>
        <p>📎 <a href="<?= e($r['attachment']['href']) ?>" target="_blank" rel="noopener"><?= e($r['attachment']['name']) ?></a></p>
    <?php elseif ($r['attachment'] !== null): ?>
        <p class="muted">📎 <?= e($r['attachment']['name']) ?></p>
    <?php else: ?>
        <p class="muted">No file attached.</p>
    <?php endif; ?>
</section>

<?php if ($r['canDecide']): ?>
<section class="card">
    <h3>Decision</h3>
    <form method="post" action="<?= e($idx) ?>?screen=approvals" class="capex-form">
        <input type="hidden" name="member_id" value="<?= e($memberId) ?>">
        <input type="hidden" name="utok" value="<?= e($user['token']) ?>">
        <input type="hidden" name="id" value="<?= e($r['id']) ?>">

        <label>Cost centre
            <select name="cost_centre">
                <option value="">— choose —</option>
                <?php foreach ($costCentres as $c): ?>
                    <option value="<?= e($c) ?>" <?= $r['costCentre'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Note <span class="muted">(optional)</span>
            <textarea name="note" rows="3" placeholder="Add a note for the record — reason, condition, etc."></textarea>
        </label>

        <div class="form-actions">
            <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
            <button type="submit" name="action" value="reject" class="btn-reject">Reject</button>
        </div>
    </form>
</section>
<?php elseif ($r['awaiting']): ?>
    <div class="alert">This request is awaiting a <strong><?= e($roleLabel[$r['required']] ?? $r['required']) ?></strong> decision — above your approval level.</div>
<?php else: ?>
    <div class="notice">This request has already been decided (<?= e($r['stage']) ?>).<?php if ($r['approvalNote'] !== ''): ?> Note: <em><?= e($r['approvalNote']) ?></em><?php endif; ?></div>
<?php endif; ?>
