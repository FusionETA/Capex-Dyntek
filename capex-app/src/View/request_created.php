<?php
/**
 * Submission confirmation.
 * @var int $id @var string $title @var string $amount @var string $approver
 */
declare(strict_types=1);
$idx = capex_base() . '/index.php';
$mid = rawurlencode((string) ($_REQUEST['member_id'] ?? ''));
$roleLabel = [
    'HOD' => 'HOD', 'REGIONAL_FIN' => 'Regional Finance', 'COUNTRY_MD' => 'Country MD', 'GROUP_CFO' => 'Group CFO',
];
?>
<h1>Request submitted</h1>

<section class="card">
    <p><strong><?= e($title) ?></strong> was created (#<?= e($id) ?>) at <strong>S$ <?= money_disp($amount) ?></strong> and is now in <strong>Submitted</strong>.</p>
    <p class="muted">It will be routed to the <strong><?= e($roleLabel[$approver] ?? $approver) ?></strong> for approval based on its amount.</p>

    <div class="form-actions">
        <a href="<?= e($idx) ?>?screen=new&amp;member_id=<?= $mid ?>" class="btn-primary">Submit another</a>
        <a href="<?= e($idx) ?>?screen=dashboard&amp;member_id=<?= $mid ?>" class="btn-link">Back to dashboard</a>
    </div>
</section>
