<?php

declare(strict_types=1);

namespace Capex\Service;

use Capex\App;
use Capex\Domain\BudgetEngine;
use Capex\Domain\Envelope;
use Capex\Domain\Money;
use Capex\Domain\Verdict;

/**
 * The budget side-effects for a single Capex Request: convert local→SGD at the
 * envelope FX rate, evaluate the verdict, write the app-owned fields back, and
 * re-derive the envelope totals. Shared by the live webhook and the in-app
 * submission form so both paths behave identically.
 */
final class RequestProcessor
{
    public function __construct(private readonly App $app)
    {
    }

    /**
     * @return array{status:string,message:string,verdict?:Verdict,envelope?:Envelope,totals?:array{committed:int,spent:int}}
     */
    public function process(int $id): array
    {
        $requests  = $this->app->requests();
        $envelopes = $this->app->envelopes();
        $recalc    = new Recalculator($requests, $envelopes, $this->app->config['stages']);

        $item = $requests->get($id);
        if ($item === []) {
            return ['status' => 'ignored', 'message' => "request {$id} not found"];
        }

        $f = $this->app->config['fields']['request'];
        $region = (string) ($item[$f['region']] ?? '');
        $fy = (int) ($this->app->config['current_fy'] ?? 0);

        $envelope = $envelopes->find($region, $fy);
        if ($envelope === null) {
            return ['status' => 'no_envelope', 'message' => "no envelope for {$region} FY{$fy}"];
        }

        $amountSgd = Money::toSGD(Money::fieldToCents($item[$f['amount_local']] ?? null), $envelope->fxRateToSgd);
        $verdict = BudgetEngine::evaluate($amountSgd, $envelope);

        $requests->update($id, [
            $f['amount_sgd']     => Money::format($amountSgd),
            $f['envelope_id']    => $envelope->id,
            $f['budget_verdict'] => $verdict->status,
            $f['over_by_sgd']    => Money::format($verdict->overBySgd),
        ]);

        $totals = $recalc->recalc($envelope);

        return [
            'status'   => 'ok',
            'message'  => sprintf('request %d: %s', $id, $verdict->status),
            'verdict'  => $verdict,
            'envelope' => $envelope,
            'totals'   => $totals,
        ];
    }
}
