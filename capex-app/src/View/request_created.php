<?php
/**
 * Submission confirmation.
 * @var int $id @var string $title
 * @var array{status:string,message:string,verdict?:object,totals?:array} $result
 */
declare(strict_types=1);
$idx = capex_base() . '/index.php';
$mid = rawurlencode((string) ($_REQUEST['member_id'] ?? ''));
$verdict = $result['verdict'] ?? null;
$over = $verdict !== null && $verdict->status === 'OVER';
?>
<h1>Request submitted</h1>

<section class="card">
    <p><strong><?= e($title) ?></strong> was created (#<?= e($id) ?>) and is now in <strong>Submitted</strong>.</p>

    <?php if ($result['status'] === 'ok' && $verdict !== null): ?>
        <?php if ($over): ?>
            <div class="alert">
                Over budget by <strong>S$ <?= money_disp(\Capex\Domain\Money::format($verdict->overBySgd)) ?></strong>.
                A reallocation note is required and this will route to the Group CFO for approval.
            </div>
        <?php else: ?>
            <p class="verdict-within">Within budget ✓ — checked against the region's available envelope.</p>
        <?php endif; ?>
    <?php elseif ($result['status'] === 'no_envelope'): ?>
        <div class="alert">No budget envelope exists for this region and fiscal year, so the budget check was skipped. Finance should create one.</div>
    <?php endif; ?>

    <div class="form-actions">
        <a href="<?= e($idx) ?>?screen=new&amp;member_id=<?= $mid ?>" class="btn-primary">Submit another</a>
        <a href="<?= e($idx) ?>?screen=dashboard&amp;member_id=<?= $mid ?>" class="btn-link">Back to dashboard</a>
    </div>
</section>
