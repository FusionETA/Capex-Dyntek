<?php

declare(strict_types=1);

namespace Capex\Http\Handlers;

use Capex\App;
use Capex\Domain\Money;
use Capex\Domain\Options;
use Capex\Domain\Roles;
use Capex\Service\ScreenData;

/**
 * Sales Targets — Finance (Carol) types the Corp target, New target and Current met
 * per region for a financial year; nothing is calculated. Regional Finance and
 * Group CFO may edit and add rows; everyone else sees it read-only. A year selector
 * switches between periods, so rolling into a new year (e.g. 2027) is just adding
 * rows — no code change. Saves are re-checked server-side against the signed role.
 */
final class Targets
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
            $canEdit = Roles::canEditTargets($user['role']);
            $flash = null;
            $fy = (string) ($_REQUEST['fy'] ?? '');

            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                if (!$canEdit) {
                    $flash = ['ok' => false, 'message' => 'Your role cannot edit sales targets.'];
                } elseif ((string) ($_POST['action'] ?? '') === 'add') {
                    $period = trim((string) ($_POST['period'] ?? ''));
                    $flash = $this->addRow((string) ($_POST['region'] ?? ''), $period);
                    if ($flash['ok']) {
                        $fy = $period; // jump to the year we just added a row for
                    }
                } else {
                    $flash = $this->save(
                        (int) ($_POST['id'] ?? 0),
                        (string) ($_POST['new_target'] ?? ''),
                        (string) ($_POST['current_met'] ?? ''),
                        (string) ($_POST['corp_target'] ?? ''),
                    );
                }
            }

            $data = (new ScreenData($this->app))->salesTargets($fy);
            $data['canEdit'] = $canEdit;
            $data['flash'] = $flash;
            $data['memberId'] = $memberId;
            $data['user'] = $user;
            $data['regions'] = Options::REGIONS;

            capex_render('targets', 'Sales Targets', 'targets', $data, $memberId, $user['token'], $user['role']);
        } catch (\Throwable $e) {
            capex_error($e);
        }
    }

    /** @return array{ok:bool,message:string} */
    private function save(int $id, string $newTarget, string $currentMet, string $corpTarget): array
    {
        if ($id === 0) {
            return ['ok' => false, 'message' => 'No target selected.'];
        }

        $f = $this->app->config['fields']['target'];
        $toCents = static fn (string $v): int => Money::toCents(str_replace([',', ' '], '', $v));

        $fields = [
            $f['target_sgd'] => Money::format($toCents($newTarget)),
            $f['actual_sgd'] => Money::format($toCents($currentMet)),
        ];
        // Corp target is stored on the record when the field exists.
        if (($c = (string) ($f['corp_target'] ?? '')) !== '' && trim($corpTarget) !== '') {
            $fields[$c] = Money::format($toCents($corpTarget));
        }

        $this->app->targets()->update($id, $fields);

        return ['ok' => true, 'message' => 'Sales target saved.'];
    }

    /** Create a target row for a region + year, guarding against duplicates. @return array{ok:bool,message:string} */
    private function addRow(string $region, string $period): array
    {
        if (!in_array($region, Options::REGIONS, true)) {
            return ['ok' => false, 'message' => 'Pick a valid region.'];
        }
        if ($period === '' || !preg_match('/^[A-Za-z0-9 \/\-]{1,20}$/', $period)) {
            return ['ok' => false, 'message' => 'Enter a valid period (e.g. 2027).'];
        }

        $f = $this->app->config['fields']['target'];
        foreach ($this->app->targets()->all() as $t) {
            if ((string) ($t[$f['region']] ?? '') === $region && (string) ($t[$f['period']] ?? '') === $period) {
                return ['ok' => false, 'message' => "A {$region} row for {$period} already exists."];
            }
        }

        $this->app->targets()->create([
            'title'       => "{$region} {$period}",
            $f['region']  => $region,
            $f['period']  => $period,
        ]);

        return ['ok' => true, 'message' => "Added {$region} row for {$period}."];
    }
}
