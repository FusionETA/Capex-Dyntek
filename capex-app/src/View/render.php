<?php

declare(strict_types=1);

/**
 * Tiny view helper. Renders a screen view inside the shared layout with the
 * given nav tab active. Views receive their model via $data (extracted in layout).
 *
 * @param array<string,mixed> $data
 */
function capex_render(string $view, string $title, string $active, array $data, string $memberId = ''): void
{
    $__view   = __DIR__ . '/' . $view . '.php';
    $__title  = $title;
    $__active = $active;
    // Bitrix only posts member_id on the initial placement load; carry it through
    // the in-app nav so tab switches stay authenticated.
    $__member = $memberId;
    require __DIR__ . '/layout.php';
}

/** HTML-escape shortcut for views. */
function e(string|int|float|null $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/**
 * Absolute base URL of the deployed public/ dir, derived from the request.
 * Relative asset/link paths break when Bitrix24 embeds the app (they resolve
 * against bitrix24.com), so every asset and nav link uses this.
 */
function capex_base(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');

    // Default https (Bitrix requires https handlers; avoids mixed-content when the
    // proxy doesn't set HTTPS). Only localhost testing uses http.
    $isLocal = (bool) preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/', $host);
    $fwd = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    $scheme = $isLocal ? 'http' : ($fwd !== '' ? $fwd : 'https');

    return $scheme . '://' . $host . $dir;
}

/**
 * Format an app-generated decimal string ("1000000.00") for display with
 * thousands separators ("1,000,000.00"). String-based to avoid float precision
 * loss on large amounts. Output is digits/commas/dot/minus only — safe to echo.
 */
function money_disp(string $decimal): string
{
    $parts = explode('.', $decimal, 2);
    $int = $parts[0];
    $frac = str_pad(substr($parts[1] ?? '00', 0, 2), 2, '0');
    $neg = ($int !== '' && $int[0] === '-');
    $int = ltrim($neg ? substr($int, 1) : $int, '0');
    if ($int === '') { $int = '0'; }
    $grouped = strrev(implode(',', str_split(strrev($int), 3)));

    return ($neg ? '-' : '') . $grouped . '.' . $frac;
}

/** Minimal 403 page for requests not coming from the installed portal. */
function capex_forbidden(): void
{
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="' . e(capex_base()) . '/assets/app.css">'
        . '<main class="capex-screen"><div class="alert">Open this app from within Bitrix24.</div></main>';
}

/** Friendly error page; the real reason goes to the log, not the screen. */
function capex_error(\Throwable $e): void
{
    error_log('[capex screen] ' . $e->getMessage());
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="' . e(capex_base()) . '/assets/app.css">'
        . '<main class="capex-screen"><div class="alert">Something went wrong loading this screen. Try again shortly.</div></main>';
}

/** Render a utilisation bar (0–100+%), red when over 100. */
function capex_bar(int $pct, bool $over = false): string
{
    $w = max(0, min(100, $pct));
    $cls = $over || $pct > 100 ? ' over' : '';
    return '<div class="bar"><span class="bar-fill' . $cls . '" style="width:' . $w . '%"></span></div>';
}
