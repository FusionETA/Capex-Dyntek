<?php

declare(strict_types=1);

namespace Capex\Http\Handlers;

use Capex\App;
use Capex\Domain\Roles;

/**
 * Manage Access — grant, change and revoke roles from inside the app. Restricted
 * to access managers (Group CFO / System Admin). Guards against lockout: a change
 * that would leave nobody able to manage access is refused, as is removing your
 * own manager role.
 */
final class Access
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
            if (!Roles::canManageAccess($user['role'])) {
                capex_forbidden();
                return;
            }

            $flash = null;
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                $flash = $this->apply((int) $user['id'], (string) ($_POST['action'] ?? ''), (int) ($_POST['user_id'] ?? 0), (string) ($_POST['role'] ?? ''));
            }

            $access = $this->app->access();
            $users = $this->app->portalUsers();

            // rows for the current access list
            $rows = [];
            foreach ($access as $uid => $role) {
                $rows[] = ['id' => $uid, 'name' => $users[$uid] ?? ('User #' . $uid), 'role' => $role];
            }
            usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));

            // users not yet granted access (for the add picker)
            $addable = array_diff_key($users, $access);

            capex_render('access', 'Manage Access', 'access', [
                'rows'     => $rows,
                'addable'  => $addable,
                'labels'   => Roles::labels(),
                'meId'     => (int) $user['id'],
                'flash'    => $flash,
                'memberId' => $memberId,
                'user'     => $user,
            ], $memberId, $user['token'], $user['role']);
        } catch (\Throwable $e) {
            capex_error($e);
        }
    }

    /** @return array{ok:bool,message:string} */
    private function apply(int $meId, string $action, int $userId, string $role): array
    {
        $access = $this->app->access();

        if ($action === 'remove') {
            if (!isset($access[$userId])) {
                return ['ok' => false, 'message' => 'That user is not on the list.'];
            }
            unset($access[$userId]);
        } elseif ($action === 'set') {
            if ($userId === 0 || !Roles::isValid($role)) {
                return ['ok' => false, 'message' => 'Pick a user and a valid role.'];
            }
            $access[$userId] = $role;
        } else {
            return ['ok' => false, 'message' => 'Unknown action.'];
        }

        // Anti-lockout: at least one access manager must remain…
        $managers = array_filter($access, static fn ($r) => Roles::canManageAccess($r));
        if ($managers === []) {
            return ['ok' => false, 'message' => 'Blocked — this would leave no one able to manage access.'];
        }
        // …and you can't strip your own ability to manage.
        if (!Roles::canManageAccess($access[$meId] ?? Roles::NONE)) {
            return ['ok' => false, 'message' => 'Blocked — you can\'t remove your own manager access.'];
        }

        $this->app->saveAccess($access);

        return ['ok' => true, 'message' => $action === 'remove' ? 'Access removed.' : 'Access saved.'];
    }
}
