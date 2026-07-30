<?php

declare(strict_types=1);

namespace Capex\Http\Handlers;

use Capex\App;
use Capex\Domain\Money;
use Capex\Service\RequestProcessor;

/**
 * In-app Capex Request submission. GET renders the form; POST creates the record
 * in the Submitted stage, runs the shared budget evaluation (so the requester
 * immediately sees WITHIN/OVER), and shows a confirmation.
 *
 * Any portal user may submit (Requester role). Approvals are a separate,
 * role-gated flow. Re-checks the caller server-side like every screen.
 */
final class NewRequest
{
    /** Option lists for the form selects. */
    private const REGIONS      = ['SG', 'HK', 'MY', 'ID'];
    private const COST_CENTRES = ['IT', 'Plant', 'Building', 'Vehicle', 'Other'];
    private const CATEGORIES   = ['IT', 'Plant & machinery', 'Building', 'Vehicle', 'Other'];
    private const CURRENCIES   = ['SGD', 'HKD', 'MYR', 'IDR'];

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
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                $this->submit($memberId);
            } else {
                $this->form($memberId, [], []);
            }
        } catch (\Throwable $e) {
            capex_error($e);
        }
    }

    /** @param array<string,string> $values @param array<int,string> $errors */
    private function form(string $memberId, array $values, array $errors): void
    {
        capex_render('new_request', 'New Capex Request', 'new', [
            'regions'      => self::REGIONS,
            'costCentres'  => self::COST_CENTRES,
            'categories'   => self::CATEGORIES,
            'currencies'   => self::CURRENCIES,
            'values'       => $values,
            'errors'       => $errors,
            'memberId'     => $memberId,
        ], $memberId);
    }

    private function submit(string $memberId): void
    {
        $in = fn (string $k): string => trim((string) ($_POST[$k] ?? ''));
        $values = [
            'title'         => $in('title'),
            'region'        => $in('region'),
            'cost_centre'   => $in('cost_centre'),
            'category'      => $in('category'),
            'amount_local'  => $in('amount_local'),
            'currency'      => $in('currency'),
            'justification' => $in('justification'),
            'payback_months' => $in('payback_months'),
        ];

        $errors = [];
        if ($values['title'] === '')         { $errors[] = 'Title is required.'; }
        if (!in_array($values['region'], self::REGIONS, true)) { $errors[] = 'Select a region.'; }
        if (!is_numeric($values['amount_local']) || (float) $values['amount_local'] <= 0) { $errors[] = 'Enter a valid amount.'; }
        if ($values['justification'] === '') { $errors[] = 'Justification is required.'; }

        if ($errors !== []) {
            $this->form($memberId, $values, $errors);
            return;
        }

        $f = $this->app->config['fields']['request'];
        $fields = [
            'title'                 => $values['title'],
            $f['region']            => $values['region'],
            $f['cost_centre']       => $values['cost_centre'],
            $f['category']          => $values['category'],
            $f['amount_local']      => Money::format(Money::toCents($values['amount_local'])),
            $f['currency']          => $values['currency'],
            $f['justification']     => $values['justification'],
            'stageId'               => $this->app->config['stages']['submitted'] ?? '',
        ];
        if ($values['payback_months'] !== '' && is_numeric($values['payback_months'])) {
            $fields[$f['payback_months']] = (int) $values['payback_months'];
        }

        $id = $this->app->requests()->create($fields);
        $result = (new RequestProcessor($this->app))->process($id);

        capex_render('request_created', 'Request submitted', 'new', [
            'id'      => $id,
            'title'   => $values['title'],
            'result'  => $result,
        ], $memberId);
    }
}
