<?php

declare(strict_types=1);

namespace Capex\Http\Handlers;

use Capex\App;
use Capex\Domain\Money;
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
                $action = (string) ($_POST['action'] ?? '');
                $flash = $action === 'set_bands'
                    ? $this->saveBands($_POST['band'] ?? [])
                    : $this->apply((int) $user['id'], $action, (int) ($_POST['user_id'] ?? 0), (string) ($_POST['role'] ?? ''));
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
                'bands'    => $this->bandRows(),
                'bandTop'  => Roles::labels()[Roles::GROUP_CFO] ?? 'Group CFO',
                'meId'     => (int) $user['id'],
                'flash'    => $flash,
                'memberId' => $memberId,
                'user'     => $user,
            ], $memberId, $user['token'], $user['role']);
        } catch (\Throwable $e) {
            capex_error($e);
        }
    }

    /**
     * The editable amount bands for the view, ascending. Each is the ceiling below
     * which that role approves; anything above the top ceiling goes to Group CFO.
     * @return array<int,array{role:string,label:string,amount:string,display:string}>
     */
    private function bandRows(): array
    {
        $labels = Roles::labels();
        $rows = [];
        foreach ($this->app->authorityBands() as $ceiling => $role) {
            $rows[] = [
                'role'    => (string) $role,
                'label'   => $labels[$role] ?? (string) $role,
                'amount'  => Money::format((int) $ceiling),   // "50000.00" for the input value
                'display' => Money::format((int) $ceiling),   // money_disp() groups it in the view
            ];
        }

        return $rows;
    }

    /**
     * Save edited amount bands. Reads one amount per current band role, keeps the
     * role→role mapping fixed (only the amounts change), and requires the ceilings
     * to be strictly ascending so routing stays sensible.
     *
     * @param mixed $posted band[<ROLE>] => amount string (SGD)
     * @return array{ok:bool,message:string}
     */
    private function saveBands(mixed $posted): array
    {
        if (!is_array($posted)) {
            return ['ok' => false, 'message' => 'No band values submitted.'];
        }

        $pairs = [];
        $prev = null;
        foreach ($this->app->authorityBands() as $role) {
            $raw = trim((string) ($posted[$role] ?? ''));
            if ($raw === '' || !is_numeric(str_replace([',', ' '], '', $raw))) {
                return ['ok' => false, 'message' => 'Enter a valid amount for every band.'];
            }
            $cents = Money::toCents(str_replace([',', ' '], '', $raw));
            if ($cents <= 0) {
                return ['ok' => false, 'message' => 'Band amounts must be greater than zero.'];
            }
            if ($prev !== null && $cents <= $prev) {
                return ['ok' => false, 'message' => 'Each band must be higher than the one above it (in seniority order).'];
            }
            $prev = $cents;
            $pairs[] = [$cents, (string) $role];
        }

        if ($pairs === []) {
            return ['ok' => false, 'message' => 'There are no bands to save.'];
        }

        $this->app->saveBands($pairs);

        return ['ok' => true, 'message' => 'Approval amount bands updated.'];
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
