<?php
/**
 * Shared chrome for every screen. Expects: $__view, $__title, $__active, $data.
 * @var string $__view @var string $__title @var string $__active @var array<string,mixed> $data
 */
declare(strict_types=1);

// Absolute base — relative asset/link paths break when Bitrix24 embeds the app.
$__base = capex_base();
$__idx  = $__base . '/index.php';
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
    <nav class="capex-nav">
        <span class="capex-brand">Capex</span>
        <a href="<?= e($__idx) ?>?screen=dashboard" class="<?= $__active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
        <a href="<?= e($__idx) ?>?screen=budget" class="<?= $__active === 'budget' ? 'active' : '' ?>">Budget</a>
        <a href="<?= e($__idx) ?>?screen=targets" class="<?= $__active === 'targets' ? 'active' : '' ?>">Targets</a>
    </nav>
    <main class="capex-screen">
        <?php extract($data); include $__view; ?>
    </main>
    <script>
        if (typeof BX24 !== 'undefined') { BX24.init(function () { BX24.fitWindow(); }); }
    </script>
</body>
</html>
