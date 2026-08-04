<?php

declare(strict_types=1);

namespace Capex\Http\Handlers;

use Capex\App;
use Capex\Domain\Money;
use Capex\Domain\Options;

/**
 * In-app Capex Request submission. GET renders the form; POST creates the record
 * in the Submitted stage and shows a confirmation with the routed approver.
 *
 * Cost centre is NOT set here — the approver assigns it at approval time. An
 * optional file attachment is uploaded to the record when the field exists.
 *
 * Only submitters (Tier 0-2 / Requester and above) may reach this — checked
 * server-side, so the hidden nav item isn't the only guard.
 */
final class NewRequest
{
    private string $userToken = '';
    private string $userRole = '';
    private int $userId = 0;

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

        $user = $this->app->resolveUser();
        if (!\Capex\Domain\Roles::canOpen($user['role'])) {
            capex_access_denied();
            return;
        }
        if (!\Capex\Domain\Roles::canSubmit($user['role'])) {
            capex_forbidden();
            return;
        }
        $this->userToken = $user['token'];
        $this->userRole = $user['role'];
        $this->userId = $user['id'];

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
        $canAttach = (string) ($this->app->config['fields']['request']['attachment'] ?? '') !== '';

        capex_render('new_request', 'New Capex Request', 'new', [
            'regions'      => Options::REGIONS,
            'categories'   => Options::CATEGORIES,
            'currencies'   => Options::CURRENCIES,
            'values'       => $values,
            'errors'       => $errors,
            'memberId'     => $memberId,
            'canAttach'    => $canAttach,
        ], $memberId, $this->userToken, $this->userRole);
    }

    private function submit(string $memberId): void
    {
        $in = fn (string $k): string => trim((string) ($_POST[$k] ?? ''));
        $values = [
            'title'         => $in('title'),
            'region'        => $in('region'),
            'category'      => $in('category'),
            'amount_local'  => $in('amount_local'),
            'currency'      => $in('currency'),
            'justification' => $in('justification'),
            'payback_months' => $in('payback_months'),
            'pic'           => $in('pic'),
            'timeline'      => $in('timeline'),
        ];

        $errors = [];
        if ($values['title'] === '')         { $errors[] = 'Title is required.'; }
        if (!in_array($values['region'], Options::REGIONS, true)) { $errors[] = 'Select a region.'; }
        if (!is_numeric($values['amount_local']) || (float) $values['amount_local'] <= 0) { $errors[] = 'Enter a valid amount.'; }
        if ($values['justification'] === '') { $errors[] = 'Justification is required.'; }

        if ($errors !== []) {
            $this->form($memberId, $values, $errors);
            return;
        }

        $f = $this->app->config['fields']['request'];
        $localCents = Money::toCents($values['amount_local']);
        $amountSgd = $this->app->toSgd($localCents, $values['currency']);

        $fields = [
            'title'             => $values['title'],
            $f['region']        => $values['region'],
            $f['category']      => $values['category'],
            $f['amount_local']  => Money::format($localCents),
            $f['currency']      => $values['currency'],
            $f['amount_sgd']    => Money::format($amountSgd),
            $f['justification'] => $values['justification'],
            $f['pic']           => $values['pic'] ?? '',
            $f['timeline']      => $values['timeline'] ?? '',
            $f['date_request']  => date('Y-m-d'),
            'stageId'           => $this->app->config['stages']['submitted'] ?? '',
        ];
        if ($values['payback_months'] !== '' && is_numeric($values['payback_months'])) {
            $fields[$f['payback_months']] = (int) $values['payback_months'];
        }
        // Optional attachment — only when the file field exists on the SPA.
        if (($file = $this->attachmentPayload()) !== null && ($c = (string) ($f['attachment'] ?? '')) !== '') {
            $fields[$c] = $file;
        }

        $id = $this->app->requests()->create($fields);

        // Log the submission for the History timeline.
        if ($id > 0) {
            $this->app->auditStore()->append($id, [
                'ts'     => date('c'),
                'type'   => 'submitted',
                'by'     => $this->userId,
                'byRole' => $this->userRole,
                'note'   => '',
            ]);
        }

        capex_render('request_created', 'Request submitted', 'new', [
            'id'       => $id,
            'title'    => $values['title'],
            'amount'   => Money::format($amountSgd),
            'approver' => \Capex\Domain\Authority::forAmount($amountSgd, $this->app->authorityBands()),
        ], $memberId, $this->userToken, $this->userRole);
    }

    /**
     * Read an uploaded file into Bitrix's [filename, base64] shape, or null if no
     * (valid) file was uploaded. Capped at 20 MB to stay within REST limits.
     * @return array{0:string,1:string}|null
     */
    private function attachmentPayload(): ?array
    {
        $file = $_FILES['attachment'] ?? null;
        if (!is_array($file) || ($file['error'] ?? \UPLOAD_ERR_NO_FILE) !== \UPLOAD_ERR_OK) {
            return null;
        }
        if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > 20 * 1024 * 1024) {
            return null;
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return null;
        }
        $data = @file_get_contents($tmp);
        if ($data === false) {
            return null;
        }
        $name = basename((string) ($file['name'] ?? 'attachment'));

        return [$name, base64_encode($data)];
    }
}
