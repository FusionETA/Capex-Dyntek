<?php

declare(strict_types=1);

namespace Capex\Http\Handlers;

use Capex\App;
use Capex\Service\ScreenData;

/**
 * Targets screen — sales target vs actual per region/period. Finance-maintained
 * in Bitrix24; read-only here.
 */
final class Targets
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
            $data = (new ScreenData($this->app))->targets();
            capex_render('targets', 'Capex Targets', 'targets', $data, (string) ($_REQUEST['member_id'] ?? ''), $user['token']);
        } catch (\Throwable $e) {
            capex_error($e);
        }
    }
}
