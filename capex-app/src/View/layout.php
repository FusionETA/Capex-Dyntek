<?php
/**
 * Shared chrome for every screen. Expects: $__view, $__title, $__active, $data.
 * @var string $__view @var string $__title @var string $__active @var array<string,mixed> $data
 */
declare(strict_types=1);

// Absolute base — relative asset/link paths break when Bitrix24 embeds the app.
$__base = capex_base();
$__idx  = $__base . '/index.php';
// Carry member_id + signed user token through nav so auth + role survive tab switches.
$__mq = ($__member ?? '') !== '' ? '&amp;member_id=' . rawurlencode($__member) : '';
$__mq .= ($__utok ?? '') !== '' ? '&amp;utok=' . rawurlencode($__utok) : '';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($__title) ?></title>
    <?php // Inline the CSS — an external <link> can fail when Bitrix24 embeds the app
          // (CSP, cache, or a stripped <head>). Inlining styles the page unconditionally.
          $__css = @file_get_contents(__DIR__ . '/../../public/assets/app.css'); ?>
    <style><?= $__css !== false ? $__css : '' ?></style>
    <script src="//api.bitrix24.com/api/v1/"></script>
</head>
<body>
    <?php $__r = $__role ?? ''; ?>
    <nav class="capex-nav">
        <span class="capex-brand">Capex</span>
        <a href="<?= e($__idx) ?>?screen=dashboard<?= $__mq ?>" class="<?= $__active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
        <?php if (\Capex\Domain\Roles::canApprove($__r)): ?>
            <a href="<?= e($__idx) ?>?screen=approvals<?= $__mq ?>" class="<?= $__active === 'approvals' ? 'active' : '' ?>">Approvals</a>
        <?php endif; ?>
        <a href="<?= e($__idx) ?>?screen=targets<?= $__mq ?>" class="<?= $__active === 'targets' ? 'active' : '' ?>">Sales Targets</a>
        <?php if (\Capex\Domain\Roles::canManageAccess($__r)): ?>
            <a href="<?= e($__idx) ?>?screen=access<?= $__mq ?>" class="<?= $__active === 'access' ? 'active' : '' ?>">Manage Access</a>
        <?php endif; ?>
        <?php if (\Capex\Domain\Roles::canSubmit($__r)): ?>
            <a href="<?= e($__idx) ?>?screen=new<?= $__mq ?>" class="nav-cta <?= $__active === 'new' ? 'active' : '' ?>">+ New request</a>
        <?php endif; ?>
    </nav>
    <main class="capex-screen">
        <?php extract($data); include $__view; ?>
    </main>
    <script>
        if (typeof BX24 !== 'undefined') { BX24.init(function () { BX24.fitWindow(); }); }
    </script>
</body>
</html>
