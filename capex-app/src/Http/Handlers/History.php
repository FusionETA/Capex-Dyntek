<?php

declare(strict_types=1);

namespace Capex\Http\Handlers;

use Capex\App;
use Capex\Domain\Roles;
use Capex\Service\History as HistoryService;

/**
 * History screen — approved + rejected requests with their submission→decision
 * timeline. Visible to approvers (HOD and above, incl. Finance) so Carol can see
 * the notes she keyed in. Group CFO / System Admin may edit a past request; every
 * edit is re-checked server-side and appended to the audit log.
 */
final class History
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
            if (!Roles::canViewHistory($user['role'])) {
                capex_forbidden();
                return;
            }

            $service = new HistoryService($this->app);
            $canEdit = Roles::canEditHistory($user['role']);
            $flash = null;

            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                $flash = $canEdit
                    ? $service->saveEdit($user['role'], $user['id'], (int) ($_POST['id'] ?? 0), $this->collectEdits())
                    : ['ok' => false, 'message' => 'Your role cannot edit history.'];
            }

            $id = (int) ($_REQUEST['id'] ?? 0);
            if ($id > 0) {
                $detail = $service->detail($id);
                if ($detail !== null) {
                    capex_render('history_detail', 'Request history', 'history', [
                        'r'        => $detail,
                        'canEdit'  => $canEdit,
                        'flash'    => $flash,
                        'memberId' => $memberId,
                        'user'     => $user,
                    ], $memberId, $user['token'], $user['role']);
                    return;
                }
            }

            capex_render('history', 'Request History', 'history', [
                'rows'     => $service->list(),
                'canEdit'  => $canEdit,
                'flash'    => $flash,
                'memberId' => $memberId,
                'user'     => $user,
            ], $memberId, $user['token'], $user['role']);
        } catch (\Throwable $e) {
            capex_error($e);
        }
    }

    /** @return array<string,string> editable presenter-key => submitted value */
    private function collectEdits(): array
    {
        $keys = ['title', 'region', 'cost_centre', 'category', 'currency', 'amount_local', 'justification', 'approval_note'];
        $out = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $_POST)) {
                $out[$k] = (string) $_POST[$k];
            }
        }

        return $out;
    }
}
