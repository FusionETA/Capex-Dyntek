<?php

declare(strict_types=1);

namespace Capex\Domain;

/**
 * Pure functions, no I/O — this is the only real business logic, so it gets tests first.
 * All amounts are integer SGD cents.
 *
 * Recalculation is DERIVED, not incremental (see build plan §4.2):
 *   committedSGD = Σ amountSGD of requests in stage Approved
 *   spentSGD     = Σ actualSGD of requests in stage Closed
 * A replayed webhook or a manual Kanban drag is therefore harmless.
 */
final class BudgetEngine
{
    /** Remaining headroom in an envelope, in SGD cents. May be negative. */
    public static function available(Envelope $e): int
    {
        return $e->approvedSgd - $e->committedSgd - $e->spentSgd;
    }

    /**
     * Derive an envelope's committed + spent totals from its member records.
     * Totals are re-summed, never incremented — so replaying a webhook or a manual
     * Kanban drag lands on the same numbers, and a rejection (which removes a
     * request from the Approved set) releases its commitment automatically.
     *
     * Year-end policy is LAPSE: the caller passes only the records belonging to
     * this envelope's own FY, so unspent commitments do not carry across years.
     * (Roll-forward would add the prior FY's unspent here — see Recalculator.)
     *
     * @param array<int,int> $approvedAmountsSgd amountSGD (cents) of each Approved request
     * @param array<int,int> $closedAmountsSgd   amountSGD (cents) of each Closed request
     * @return array{committed:int,spent:int}
     */
    public static function resum(array $approvedAmountsSgd, array $closedAmountsSgd): array
    {
        return [
            'committed' => array_sum($approvedAmountsSgd),
            'spent'     => array_sum($closedAmountsSgd),
        ];
    }

    /** Does $amountSgd fit within the envelope's remaining headroom? */
    public static function evaluate(int $amountSgd, Envelope $e): Verdict
    {
        $available = self::available($e);
        $overBy = $amountSgd - $available;

        return $overBy > 0
            ? new Verdict(Verdict::OVER, $overBy)
            : new Verdict(Verdict::WITHIN, 0);
    }

    /**
     * Route to the approving authority by amount band. An OVER verdict always
     * escalates to Group CFO regardless of amount.
     *
     * @param array<int,string> $bands  ascending [ceilingCents => role]; above top => GROUP_CFO
     */
    public static function authorityFor(int $amountSgd, Verdict $v, array $bands): string
    {
        if ($v->status === Verdict::OVER) {
            return 'GROUP_CFO';
        }

        foreach ($bands as $ceilingCents => $role) {
            if ($amountSgd <= $ceilingCents) {
                return $role;
            }
        }

        return 'GROUP_CFO';
    }
}
