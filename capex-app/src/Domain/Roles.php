<?php

declare(strict_types=1);

namespace Capex\Domain;

/**
 * Role identifiers + ranking. Tokens match BudgetEngine::authorityFor() output and
 * the config authority_bands, so a band's required role can be compared directly to
 * a user's role. System Admin is deliberately un-ranked for approvals (config +
 * budget edit, but never approve — build plan §3.4).
 */
final class Roles
{
    public const REQUESTER    = 'REQUESTER';
    public const HOD          = 'HOD';
    public const REGIONAL_FIN = 'REGIONAL_FIN';
    public const COUNTRY_MD   = 'COUNTRY_MD';
    public const GROUP_CFO    = 'GROUP_CFO';
    public const SYSTEM_ADMIN = 'SYSTEM_ADMIN';

    /** Approval seniority. SYSTEM_ADMIN = -1 → cannot approve anything. */
    private const RANK = [
        self::REQUESTER    => 0,
        self::HOD          => 1,
        self::REGIONAL_FIN => 2,
        self::COUNTRY_MD   => 3,
        self::GROUP_CFO    => 4,
        self::SYSTEM_ADMIN => -1,
    ];

    public static function rank(string $role): int
    {
        return self::RANK[$role] ?? 0;
    }

    public static function isValid(string $role): bool
    {
        return isset(self::RANK[$role]);
    }

    /**
     * Can $role approve where $required is the minimum authority? A higher-ranked
     * role may always stand in for a lower gate. SYSTEM_ADMIN never can.
     */
    public static function meets(string $role, string $required): bool
    {
        return self::rank($role) >= 1 && self::rank($role) >= self::rank($required);
    }
}
