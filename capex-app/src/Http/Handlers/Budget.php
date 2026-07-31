<?php

declare(strict_types=1);

namespace Capex\Http\Handlers;

use Capex\App;
use Capex\Service\ScreenData;

/**
 * Budget screen — envelope vs committed vs spent per region, over-budget alert.
 * Read-only view; envelopes are edited in Bitrix24 under its own permissions.
 */
final class Budget
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
            $data = (new ScreenData($this->app))->budget();
            capex_render('budget', 'Capex Budget', 'budget', $data, (string) ($_REQUEST['member_id'] ?? ''), $user['token']);
        } catch (\Throwable $e) {
            capex_error($e);
        }
    }
}
