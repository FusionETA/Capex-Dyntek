<?php

declare(strict_types=1);

/**
 * PSR-4-ish autoloader for the Capex\ namespace, mapped to src/.
 * No Composer required for the app to run on cPanel.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Capex\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
