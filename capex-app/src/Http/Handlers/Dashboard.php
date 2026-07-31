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

        $memberId = (string) ($_REQUEST['member_id'] ?? '');
        if (!$this->app->verifyCaller($memberId)) {
            capex_forbidden();
            return;
        }

        try {
            $user = $this->app->resolveUser();
            if (!\Capex\Domain\Roles::canOpen($user['role'])) {
                capex_access_denied();
                return;
            }
            $data = (new ScreenData($this->app))->dashboard();
            capex_render('dashboard', 'Capex Dashboard', 'dashboard', $data, $memberId, $user['token'], $user['role']);
        } catch (\Throwable $e) {
            capex_error($e);
        }
    }
}
