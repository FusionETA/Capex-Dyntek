<?php

declare(strict_types=1);

namespace Capex\Http\Handlers;

use Capex\App;
use Capex\Domain\BudgetEngine;
use Capex\Domain\Money;
use Capex\Service\Recalculator;

/**
 * Handles onCrmDynamicItemUpdate for the Capex Request entity (build plan §4.3):
 *   verify -> load request -> set amountSGD, envelopeId, verdict, overBy
 *   -> re-sum the affected envelope (DERIVED, not incremental) -> write back.
 *
 * Idempotent: a replayed or duplicate event lands on the same figures, so we just
 * log and return 200. Every early exit is still a 200 — Bitrix retries non-2xx.
 */
final class Webhook
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(): void
    {
        // Bitrix delivers auth[member_id] + auth[application_token] with the event.
        $auth = (array) ($_REQUEST['auth'] ?? []);
        $memberId = (string) ($auth['member_id'] ?? '');

        // TODO(M5): also compare auth[application_token] against the value stored at install.
        if (!$this->app->verifyCaller($memberId)) {
            $this->ok('ignored: unrecognised portal');
            return;
        }

        $fields = (array) ($_REQUEST['data']['FIELDS'] ?? []);
        $requestEntityTypeId = (int) ($this->app->config['entities']['request'] ?? 0);
        $entityTypeId = (int) ($fields['ENTITY_TYPE_ID'] ?? 0);
        $id = (int) ($fields['ID'] ?? 0);

        if ($id === 0 || ($entityTypeId !== 0 && $entityTypeId !== $requestEntityTypeId)) {
            $this->ok('ignored: not a capex request');
            return;
        }

        $requests  = $this->app->requests();
        $envelopes = $this->app->envelopes();
        $recalc    = new Recalculator($requests, $envelopes, $this->app->config['stages']);

        $item = $requests->get($id);
        if ($item === []) {
            $this->ok("ignored: request {$id} not found");
            return;
        }

        $f = $this->app->config['fields']['request'];
        $region = (string) ($item[$f['region']] ?? '');
        $fy = (int) ($this->app->config['current_fy'] ?? 0);

        $envelope = $envelopes->find($region, $fy);
        if ($envelope === null) {
            $this->ok("ignored: no envelope for {$region} FY{$fy}");
            return;
        }

        // Convert the requester's local amount to SGD at the envelope's FX rate.
        $amountLocalCents = Money::fieldToCents($item[$f['amount_local']] ?? null);
        $amountSgd = Money::toSGD($amountLocalCents, $envelope->fxRateToSgd);

        $verdict = BudgetEngine::evaluate($amountSgd, $envelope);

        // Write the app-owned fields back onto the request.
        $requests->update($id, [
            $f['amount_sgd']     => Money::format($amountSgd),
            $f['envelope_id']    => $envelope->id,
            $f['budget_verdict'] => $verdict->status,
            $f['over_by_sgd']    => Money::format($verdict->overBySgd),
        ]);

        // Re-derive the envelope totals from live records (replay-safe).
        $totals = $recalc->recalc($envelope);

        $this->ok(sprintf(
            'request %d: %s, committed=%s spent=%s',
            $id,
            $verdict->status,
            Money::format($totals['committed']),
            Money::format($totals['spent']),
        ));
    }

    private function ok(string $message): void
    {
        error_log('[capex webhook] ' . $message);
        http_response_code(200);
        echo 'OK';
    }
}
