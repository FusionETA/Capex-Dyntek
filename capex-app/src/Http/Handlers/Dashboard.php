<?php

declare(strict_types=1);

namespace Capex\Http\Handlers;

/**
 * Dashboard screen — regional KPIs, sales-target progress, approved capex ranking.
 * Visible to all; still re-checks rights server-side. Ported from the signed-off
 * prototype (Capex System.dc.html). TODO(M4).
 */
final class Dashboard
{
    public function handle(): void
    {
        require __DIR__ . '/../../View/dashboard.php';
    }
}
