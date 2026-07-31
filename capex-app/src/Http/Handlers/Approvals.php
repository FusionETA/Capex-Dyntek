<?php

declare(strict_types=1);

namespace Capex\Http\Handlers;

use Capex\App;
use Capex\Service\Approvals as ApprovalsService;

/**
 * Approvals screen — requests awaiting a decision the viewer's role can make.
 * POST performs approve/reject, re-checked server-side against the signed role
 * (never the raw request), since the app moves stages with the service token.
 */
final class Approvals
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
            $service = new ApprovalsService($this->app);
            $flash = null;

            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                $id = (int) ($_POST['id'] ?? 0);
                $action = (string) ($_POST['action'] ?? '');
                $flash = $service->act($user['role'], $id, $action);
            }

            capex_render('approvals', 'Capex Approvals', 'approvals', [
                'rows'     => $service->pending($user['role']),
                'user'     => $user,
                'flash'    => $flash,
                'memberId' => $memberId,
            ], $memberId, $user['token']);
        } catch (\Throwable $e) {
            capex_error($e);
        }
    }
}
