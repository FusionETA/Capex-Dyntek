<?php

declare(strict_types=1);

namespace Capex\Service;

use Capex\App;
use Capex\Domain\BudgetEngine;
use Capex\Domain\Money;
use Capex\Domain\Roles;
use Capex\Domain\Verdict;

/**
 * In-app approval flow. Two gates over the existing stages:
 *   Gate A (HOD): request at Submitted or HOD review → Approve moves to Finance
 *     review, Reject to Rejected. Any HOD-or-higher role may act.
 *   Gate B (authority band): request at Finance review → the band-required role
 *     (BudgetEngine::authorityFor) or higher approves to Approved; OVER-budget
 *     always needs Group CFO. Reject to Rejected.
 *
 * Because the app moves stages with the admin service token, every action is
 * re-checked server-side against the caller's signed role (see act()).
 */
final class Approvals
{
    public function __construct(private readonly App $app)
    {
    }

    /**
     * Requests currently awaiting a decision the given role can make.
     * @return array<int,array<string,mixed>>
     */
    public function pending(string $role): array
    {
        $requests = $this->app->requests();
        $stages = $this->app->config['stages'];
        $gateAStages = [$stages['submitted'] ?? '', $stages['hod_review'] ?? ''];
        $gateBStage = $stages['finance_review'] ?? '';

        $rows = [];
        foreach (array_merge($gateAStages, [$gateBStage]) as $stageId) {
            if ($stageId === '') {
                continue;
            }
            foreach ($requests->inStage($stageId) as $item) {
                $meta = $this->classify($item, $stageId);
                if ($meta['canAct'] ? Roles::meets($role, $meta['required']) : false) {
                    $rows[] = $meta;
                }
            }
        }

        return $rows;
    }

    /**
     * Perform an approve/reject. Verifies the role may act at the item's gate, moves
     * the stage, and re-derives the envelope. Returns [ok, message].
     *
     * @return array{ok:bool,message:string}
     */
    public function act(string $role, int $requestId, string $action): array
    {
        $requests = $this->app->requests();
        $stages = $this->app->config['stages'];

        $item = $requests->get($requestId);
        if ($item === []) {
            return ['ok' => false, 'message' => 'Request not found.'];
        }

        $stageId = (string) ($item['stageId'] ?? '');
        $meta = $this->classify($item, $stageId);

        if (!$meta['canAct']) {
            return ['ok' => false, 'message' => 'This request is not awaiting approval.'];
        }
        if (!Roles::meets($role, $meta['required'])) {
            return ['ok' => false, 'message' => 'Your role cannot approve this request.'];
        }

        if ($action === 'reject') {
            $target = $stages['rejected'];
        } elseif ($action === 'approve') {
            $target = $meta['gate'] === 'A' ? $stages['finance_review'] : $stages['approved'];
        } else {
            return ['ok' => false, 'message' => 'Unknown action.'];
        }

        $requests->update($requestId, ['stageId' => $target]);

        // Re-derive the envelope after the stage change (approve/reject shifts totals).
        (new RequestProcessor($this->app))->process($requestId);

        return ['ok' => true, 'message' => sprintf('Request #%d %s.', $requestId, $action === 'approve' ? 'approved' : 'rejected')];
    }

    /**
     * Work out an item's gate, the role required to approve it, and display fields.
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private function classify(array $item, string $stageId): array
    {
        $stages = $this->app->config['stages'];
        $f = $this->app->config['fields']['request'];

        $gate = null;
        if (in_array($stageId, [$stages['submitted'] ?? '', $stages['hod_review'] ?? ''], true)) {
            $gate = 'A';
        } elseif ($stageId === ($stages['finance_review'] ?? '')) {
            $gate = 'B';
        }

        $amountSgd = Money::fieldToCents($item[$f['amount_sgd']] ?? null);
        $verdictStatus = (string) ($item[$f['budget_verdict']] ?? Verdict::WITHIN);
        $overBy = Money::fieldToCents($item[$f['over_by_sgd']] ?? null);
        $verdict = new Verdict($verdictStatus === Verdict::OVER ? Verdict::OVER : Verdict::WITHIN, $overBy);

        // Gate A only needs HOD; Gate B needs the amount-band authority (OVER → CFO).
        $required = $gate === 'B'
            ? BudgetEngine::authorityFor($amountSgd, $verdict, $this->app->config['authority_bands'] ?? [])
            : Roles::HOD;

        return [
            'id'       => (int) ($item['id'] ?? 0),
            'title'    => (string) ($item['title'] ?? 'Untitled'),
            'region'   => (string) ($item[$f['region']] ?? '—'),
            'amount'   => Money::format($amountSgd),
            'verdict'  => $verdict->status,
            'gate'     => $gate,
            'required' => $required,
            'canAct'   => $gate !== null,
        ];
    }
}
