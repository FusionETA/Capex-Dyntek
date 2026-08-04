<?php
/**
 * History list — approved + rejected requests, newest decision first.
 * @var array<int,array<string,mixed>> $rows
 * @var bool $canEdit
 * @var array{ok:bool,message:string}|null $flash
 * @var string $memberId
 * @var array{id:int,role:string,token:string} $user
 */
declare(strict_types=1);
$idx = capex_base() . '/index.php';
$mq = ($memberId !== '' ? '&amp;member_id=' . rawurlencode($memberId) : '')
    . ($user['token'] !== '' ? '&amp;utok=' . rawurlencode($user['token']) : '');
$day = static fn (string $ts): string => $ts === '' ? '—' : e(substr($ts, 0, 10));
?>
<h1>History</h1>
<p class="muted">Approved and rejected requests, with their submission and decision timeline.
    <?= $canEdit ? 'You can open a request to edit it — every change is logged.' : '' ?></p>

<?php if ($flash !== null): ?>
    <div class="<?= $flash['ok'] ? 'notice' : 'alert' ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<section class="card">
    <?php if (!$rows): ?>
        <p class="muted">No approved or rejected requests yet.</p>
    <?php else: ?>
    <table class="grid">
        <thead><tr><th>#</th><th>Request</th><th>Region</th><th class="num">Amount (SGD)</th><th>Outcome</th><th>Submitted</th><th>Approved</th><th>Note</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['id']) ?></td>
                <td><a href="<?= e($idx) ?>?screen=history&amp;id=<?= e($r['id']) ?><?= $mq ?>"><?= e($r['title']) ?></a></td>
                <td><strong><?= e($r['region']) ?></strong></td>
                <td class="num"><?= money_disp($r['amount']) ?></td>
                <td><span class="chip <?= $r['stage'] === 'Approved' ? 'chip-ok' : 'chip-no' ?>"><?= e($r['stage']) ?></span></td>
                <td><?= $day($r['submitted']) ?></td>
                <td><?= $r['stage'] === 'Approved' ? $day($r['decidedOn']) : '—' ?></td>
                <td class="muted"><?= e($r['note'] !== '' ? $r['note'] : '—') ?></td>
                <td><a href="<?= e($idx) ?>?screen=history&amp;id=<?= e($r['id']) ?><?= $mq ?>" class="btn-link"><?= $canEdit ? 'Open / edit →' : 'View →' ?></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
