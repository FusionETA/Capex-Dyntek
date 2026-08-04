<?php

declare(strict_types=1);

namespace Capex\Service;

use Capex\App;
use Capex\Domain\Authority;
use Capex\Domain\Money;
use Capex\Domain\Roles;

/**
 * In-app approval. A submitted request is routed by its SGD amount to the
 * required authority (Authority::forAmount); that role — or any higher one —
 * opens the request, optionally sets the cost centre and a note, and approves it
 * to Approved or rejects it. No budget is involved.
 *
 * The app moves stages with the admin service token, so every action is
 * re-checked server-side against the caller's signed role. Every action is also
 * written to the audit log for the History timeline.
 */
final class Approvals
{
    public function __construct(private readonly App $app)
    {
    }

    /**
     * Submitted requests this role is allowed to decide.
     * @return array<int,array<string,mixed>>
     */
    public function pending(string $role): array
    {
        $submitted = $this->app->config['stages']['submitted'] ?? '';
        if ($submitted === '') {
            return [];
        }

        $rows = [];
        foreach ($this->app->requests()->inStage($submitted) as $item) {
            $meta = $this->classify($item);
            if (Roles::meets($role, $meta['required'])) {
                $rows[] = $meta;
            }
        }

        return $rows;
    }

    /**
     * Full detail for one request plus whether this role may decide it now.
     * Returns null if the request doesn't exist.
     * @return array<string,mixed>|null
     */
    public function detail(int $requestId, string $role): ?array
    {
        $item = $this->app->requests()->get($requestId);
        if ($item === []) {
            return null;
        }

        $view = (new RequestPresenter($this->app))->present($item);
        $required = Authority::forAmount(
            Money::fieldToCents($item[$this->app->config['fields']['request']['amount_sgd'] ?? ''] ?? null),
            $this->app->authorityBands(),
        );
        $submitted = $this->app->config['stages']['submitted'] ?? '';

        $view['required']  = $required;
        $view['canDecide'] = ($view['stageId'] === $submitted) && Roles::meets($role, $required);
        $view['awaiting']  = ($view['stageId'] === $submitted);

        return $view;
    }

    /**
     * Approve or reject. Verifies the caller's role can clear the request's band,
     * optionally sets the cost centre + note, moves the stage, stamps the approval
     * date, and appends an audit event. Returns [ok, message].
     * @return array{ok:bool,message:string}
     */
    public function act(string $role, int $userId, int $requestId, string $action, string $today, string $note = '', string $costCentre = ''): array
    {
        $requests = $this->app->requests();
        $stages = $this->app->config['stages'];
        $f = $this->app->config['fields']['request'];

        $item = $requests->get($requestId);
        if ($item === []) {
            return ['ok' => false, 'message' => 'Request not found.'];
        }
        if ((string) ($item['stageId'] ?? '') !== ($stages['submitted'] ?? '')) {
            return ['ok' => false, 'message' => 'This request is not awaiting approval.'];
        }

        $meta = $this->classify($item);
        if (!Roles::meets($role, $meta['required'])) {
            return ['ok' => false, 'message' => 'Your role cannot approve this request.'];
        }

        if ($action !== 'approve' && $action !== 'reject') {
            return ['ok' => false, 'message' => 'Unknown action.'];
        }

        $note = trim($note);
        $fields = [];

        if ($action === 'approve') {
            $fields['stageId'] = $stages['approved'];
            if (($c = (string) ($f['date_approval'] ?? '')) !== '') {
                $fields[$c] = $today;
            }
            // Cost centre is decided here, at approval time.
            if ($costCentre !== '' && ($c = (string) ($f['cost_centre'] ?? '')) !== '') {
                $fields[$c] = $costCentre;
            }
        } else {
            $fields['stageId'] = $stages['rejected'];
        }

        // Persist the note to the record too (visible in Bitrix), when the field exists.
        if ($note !== '' && ($c = (string) ($f['approval_note'] ?? '')) !== '') {
            $fields[$c] = $note;
        }

        $requests->update($requestId, $fields);

        $this->app->auditStore()->append($requestId, [
            'ts'         => date('c'),
            'type'       => $action === 'approve' ? 'approved' : 'rejected',
            'by'         => $userId,
            'byRole'     => $role,
            'note'       => $note,
            'costCentre' => $action === 'approve' ? $costCentre : '',
        ]);

        $verb = $action === 'approve' ? 'approved' : 'rejected';
        return ['ok' => true, 'message' => sprintf('Request #%d %s.', $requestId, $verb)];
    }

    /**
     * Display fields + the role required to approve, from the amount band.
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private function classify(array $item): array
    {
        $f = $this->app->config['fields']['request'];
        $amount = Money::fieldToCents($item[$f['amount_sgd']] ?? null);

        return [
            'id'       => (int) ($item['id'] ?? 0),
            'title'    => (string) ($item['title'] ?? 'Untitled'),
            'region'   => (string) ($item[$f['region']] ?? '—'),
            'pic'      => (string) ($item[$f['pic']] ?? ''),
            'amount'   => Money::format($amount),
            'required' => Authority::forAmount($amount, $this->app->authorityBands()),
        ];
    }
}
