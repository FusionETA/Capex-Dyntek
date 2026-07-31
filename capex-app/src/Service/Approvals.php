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
 * approves it to Approved or rejects it. No budget is involved.
 *
 * The app moves stages with the admin service token, so every action is
 * re-checked server-side against the caller's signed role.
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
     * Approve or reject. Verifies the caller's role can clear the request's band,
     * moves the stage, and stamps the approval date. Returns [ok, message].
     * @return array{ok:bool,message:string}
     */
    public function act(string $role, int $requestId, string $action, string $today): array
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

        if ($action === 'approve') {
            $requests->update($requestId, [
                'stageId'            => $stages['approved'],
                $f['date_approval']  => $today,
            ]);
            return ['ok' => true, 'message' => sprintf('Request #%d approved.', $requestId)];
        }

        if ($action === 'reject') {
            $requests->update($requestId, ['stageId' => $stages['rejected']]);
            return ['ok' => true, 'message' => sprintf('Request #%d rejected.', $requestId)];
        }

        return ['ok' => false, 'message' => 'Unknown action.'];
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
            'required' => Authority::forAmount($amount, $this->app->config['authority_bands'] ?? []),
        ];
    }
}
