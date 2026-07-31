<?php

declare(strict_types=1);

namespace Capex\Http\Handlers;

use Capex\App;
use Capex\Domain\Money;
use Capex\Domain\Roles;
use Capex\Service\ScreenData;

/**
 * Sales Targets — Finance (Carol) types the New target and Current met per region;
 * nothing is calculated. Regional Finance and Group CFO may edit; everyone else
 * sees it read-only. Saves are re-checked server-side against the signed role.
 */
final class Targets
{
    private const CAN_EDIT = [Roles::REGIONAL_FIN, Roles::GROUP_CFO];

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
            $canEdit = in_array($user['role'], self::CAN_EDIT, true);
            $flash = null;

            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                $flash = $canEdit
                    ? $this->save((int) ($_POST['id'] ?? 0), (string) ($_POST['new_target'] ?? ''), (string) ($_POST['current_met'] ?? ''))
                    : ['ok' => false, 'message' => 'Your role cannot edit sales targets.'];
            }

            $data = (new ScreenData($this->app))->salesTargets();
            $data['canEdit'] = $canEdit;
            $data['flash'] = $flash;
            $data['memberId'] = $memberId;

            capex_render('targets', 'Sales Targets', 'targets', $data, $memberId, $user['token']);
        } catch (\Throwable $e) {
            capex_error($e);
        }
    }

    /** @return array{ok:bool,message:string} */
    private function save(int $id, string $newTarget, string $currentMet): array
    {
        if ($id === 0) {
            return ['ok' => false, 'message' => 'No target selected.'];
        }

        $f = $this->app->config['fields']['target'];
        $toCents = static fn (string $v): int => Money::toCents(str_replace([',', ' '], '', $v));

        $this->app->targets()->update($id, [
            $f['target_sgd'] => Money::format($toCents($newTarget)),
            $f['actual_sgd'] => Money::format($toCents($currentMet)),
        ]);

        return ['ok' => true, 'message' => 'Sales target saved.'];
    }
}
