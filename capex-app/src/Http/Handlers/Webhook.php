<?php

declare(strict_types=1);

namespace Capex\Http\Handlers;

/**
 * Handles onCrmDynamicItemUpdate for the Capex Request entity.
 *
 * Flow (build plan §4.3):
 *   verify token -> load request -> set amountSGD, envelopeId, verdict, overBy
 *   -> re-sum the affected envelope (DERIVED, not incremental) -> write back.
 * Idempotent: log and return 200 on a duplicate/replayed event.
 *
 * TODO(M3): wire to Requests/Envelopes repos + BudgetEngine.
 */
final class Webhook
{
    public function handle(): void
    {
        // 1. verify shared webhook token
        // 2. load the request by id
        // 3. compute amountSGD via Money::toSGD at the envelope FX rate
        // 4. evaluate verdict via BudgetEngine
        // 5. re-sum the envelope's committed/spent from member records
        // 6. write back to the request and the envelope
        http_response_code(200);
        echo 'OK';
    }
}
