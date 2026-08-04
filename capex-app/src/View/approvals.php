<?php
/**
 * Approvals list. Each row links into the detail view, where the decision is made.
 * @var array<int,array<string,mixed>> $rows
 * @var array{id:int,role:string,token:string} $user
 * @var array{ok:bool,message:string}|null $flash
 * @var string $memberId
 */
declare(strict_types=1);
$idx = capex_base() . '/index.php';
$mq = ($memberId !== '' ? '&amp;member_id=' . rawurlencode($memberId) : '')
    . ($user['token'] !== '' ? '&amp;utok=' . rawurlencode($user['token']) : '');
$roleLabel = [
    'REQUESTER' => 'Requester', 'HOD' => 'HOD', 'REGIONAL_FIN' => 'Regional Finance',
    'COUNTRY_MD' => 'Country MD', 'GROUP_CFO' => 'Group CFO', 'SYSTEM_ADMIN' => 'System Admin',
];
$myRole = $roleLabel[$user['role']] ?? $user['role'];
?>
<h1>Approvals</h1>
<p class="muted">Requests awaiting your decision. Your role: <span class="chip"><?= e($myRole) ?></span></p>

<?php if ($flash !== null): ?>
    <div class="<?= $flash['ok'] ? 'notice' : 'alert' ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<section class="card">
    <?php if ($user['id'] === 0): ?>
        <p class="muted">Open this screen from within Bitrix24 so we can identify you and your approval role.</p>
    <?php elseif (!$rows): ?>
        <p class="muted">Nothing awaiting your approval right now.</p>
    <?php else: ?>
    <table class="grid">
        <thead><tr><th>#</th><th>Request</th><th>Region</th><th>PIC</th><th class="num">Amount (SGD)</th><th>Approver</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['id']) ?></td>
                <td><a href="<?= e($idx) ?>?screen=approvals&amp;id=<?= e($r['id']) ?><?= $mq ?>"><?= e($r['title']) ?></a></td>
                <td><strong><?= e($r['region']) ?></strong></td>
                <td><?= e($r['pic']) ?></td>
                <td class="num"><?= money_disp($r['amount']) ?></td>
                <td><span class="chip"><?= e($roleLabel[$r['required']] ?? $r['required']) ?></span></td>
                <td><a href="<?= e($idx) ?>?screen=approvals&amp;id=<?= e($r['id']) ?><?= $mq ?>" class="btn-link">Review →</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
