<?php

declare(strict_types=1);

namespace Capex\Http\Handlers;

use Capex\App;
use Capex\Service\ScreenData;

/**
 * Dashboard screen — regional KPIs, sales-target progress, approved capex ranking.
 * Visible to all portal users; still re-checks the caller server-side.
 */
final class Dashboard
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(): void
    {
        require_once __DIR__ . '/../../View/render.php';

        if (!$this->app->verifyCaller((string) ($_REQUEST['member_id'] ?? ''))) {
            capex_forbidden();
            return;
        }

        try {
            $user = $this->app->resolveUser();
            $data = (new ScreenData($this->app))->dashboard();
            capex_render('dashboard', 'Capex Dashboard', 'dashboard', $data, (string) ($_REQUEST['member_id'] ?? ''), $user['token']);
        } catch (\Throwable $e) {
            capex_error($e);
        }
    }
}
