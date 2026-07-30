<?php

declare(strict_types=1);

namespace Capex\Service;

use Capex\Domain\BudgetEngine;
use Capex\Domain\Envelope;
use Capex\Repo\Envelopes;
use Capex\Repo\Requests;

/**
 * Derives an envelope's committed + spent totals by summing its member records
 * and writes them back. This is the safety-net used by both the live webhook and
 * the nightly cron: because totals are re-derived (never incremented), running it
 * twice — or after a manual Kanban drag — always converges on the same figures.
 *
 * Year-end policy is LAPSE: only records linked to this envelope (its own FY) are
 * summed. To switch to roll-forward, add the prior FY's unspent commitment to the
 * committed side here — the pure BudgetEngine::resum stays unchanged.
 */
final class Recalculator
{
    /** @param array<string,string> $stages config stage codes */
    public function __construct(
        private readonly Requests $requests,
        private readonly Envelopes $envelopes,
        private readonly array $stages,
    ) {
    }

    /**
     * Re-sum the given envelope from live records and persist the totals.
     *
     * @return array{committed:int,spent:int} the freshly derived totals, in cents
     */
    public function recalc(Envelope $envelope): array
    {
        $approved = $this->requests->amountsSgdInStageForEnvelope(
            $this->stages['approved'],
            $envelope->id,
        );
        $closed = $this->requests->amountsSgdInStageForEnvelope(
            $this->stages['closed'],
            $envelope->id,
        );

        $totals = BudgetEngine::resum($approved, $closed);

        $this->envelopes->writeTotals($envelope->id, $totals['committed'], $totals['spent']);

        return $totals;
    }
}
