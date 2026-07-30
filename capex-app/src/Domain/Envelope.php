<?php

declare(strict_types=1);

namespace Capex\Domain;

/**
 * Value object for a Budget Envelope. All amounts are integer SGD cents.
 * Kept separate from the Repo layer so BudgetEngine stays pure and testable.
 */
final class Envelope
{
    public function __construct(
        public readonly int $id,
        public readonly string $region,
        public readonly int $fy,
        public readonly int $approvedSgd,
        public readonly int $committedSgd,
        public readonly int $spentSgd,
        public readonly float $fxRateToSgd,
        public readonly string $status, // draft | locked
    ) {
    }
}
