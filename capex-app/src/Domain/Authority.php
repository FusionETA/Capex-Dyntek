<?php

declare(strict_types=1);

namespace Capex\Domain;

/**
 * Delegation of authority: which role must approve a request of a given size.
 * Pure amount-band routing — no budget involved. Bands are ascending
 * [ceilingCents => role]; anything above the top band needs the Group CFO.
 */
final class Authority
{
    /**
     * @param array<int,string> $bands ascending ceilingCents => role
     */
    public static function forAmount(int $amountSgdCents, array $bands): string
    {
        foreach ($bands as $ceiling => $role) {
            if ($amountSgdCents <= $ceiling) {
                return $role;
            }
        }

        return Roles::GROUP_CFO;
    }
}
