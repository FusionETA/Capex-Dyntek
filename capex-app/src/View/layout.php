<?php
/**
 * Shared chrome for every screen. Expects: $__view, $__title, $__active, $data.
 * @var string $__view @var string $__title @var string $__active @var array<string,mixed> $data
 */
declare(strict_types=1);
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($__title) ?></title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
    <nav class="capex-nav">
        <span class="capex-brand">Capex</span>
        <a href="index.php?screen=dashboard" class="<?= $__active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
        <a href="index.php?screen=budget" class="<?= $__active === 'budget' ? 'active' : '' ?>">Budget</a>
        <a href="index.php?screen=targets" class="<?= $__active === 'targets' ? 'active' : '' ?>">Targets</a>
    </nav>
    <main class="capex-screen">
        <?php extract($data); include $__view; ?>
    </main>
</body>
</html>
