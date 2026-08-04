<?php

declare(strict_types=1);

namespace Capex\Http\Handlers;

use Capex\App;
use Capex\Domain\Options;
use Capex\Domain\Roles;
use Capex\Service\Approvals as ApprovalsService;

/**
 * Approvals screen — requests awaiting a decision the viewer's role can make.
 * The list links into a per-request detail view; the approve/reject decision
 * (with an optional note and the cost centre) is made there. POST performs the
 * action, re-checked server-side against the signed role (never the raw request),
 * since the app moves stages with the service token.
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
            if (!Roles::canOpen($user['role'])) {
                capex_access_denied();
                return;
            }
            if (!Roles::canApprove($user['role'])) {
                capex_forbidden();
                return;
            }

            $service = new ApprovalsService($this->app);
            $flash = null;

            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                $flash = $service->act(
                    $user['role'],
                    $user['id'],
                    (int) ($_POST['id'] ?? 0),
                    (string) ($_POST['action'] ?? ''),
                    date('Y-m-d'),
                    (string) ($_POST['note'] ?? ''),
                    (string) ($_POST['cost_centre'] ?? ''),
                );
            }

            // Detail view for a single request (unless it was just decided — then
            // fall back to the list so the user sees the updated queue).
            $id = (int) ($_REQUEST['id'] ?? 0);
            if ($id > 0 && ($flash === null || !$flash['ok'])) {
                $detail = $service->detail($id, $user['role']);
                if ($detail !== null) {
                    capex_render('approval_detail', 'Review request', 'approvals', [
                        'r'           => $detail,
                        'costCentres' => Options::COST_CENTRES,
                        'flash'       => $flash,
                        'memberId'    => $memberId,
                        'user'        => $user,
                    ], $memberId, $user['token'], $user['role']);
                    return;
                }
            }

            capex_render('approvals', 'Capex Approvals', 'approvals', [
                'rows'     => $service->pending($user['role']),
                'user'     => $user,
                'flash'    => $flash,
                'memberId' => $memberId,
            ], $memberId, $user['token'], $user['role']);
        } catch (\Throwable $e) {
            capex_error($e);
        }
    }
}
