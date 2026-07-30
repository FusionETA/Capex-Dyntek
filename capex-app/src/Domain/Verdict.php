<?php

declare(strict_types=1);

namespace Capex\Domain;

/** Result of a budget check. overBySgd is 0 when WITHIN. */
final class Verdict
{
    public const WITHIN = 'WITHIN';
    public const OVER   = 'OVER';

    public function __construct(
        public readonly string $status, // WITHIN | OVER
        public readonly int $overBySgd, // integer SGD cents, 0 when WITHIN
    ) {
    }
}
