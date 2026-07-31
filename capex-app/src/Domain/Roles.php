<?php

declare(strict_types=1);

namespace Capex\Domain;

/**
 * Access roles + the capabilities each grants. Closed by default: a user with no
 * assigned role (NONE) cannot open the app at all. Approval seniority is ranked so
 * a band's required role can be compared to a user's role; capabilities that aren't
 * a simple seniority (submit, edit targets) are explicit sets.
 */
final class Roles
{
    public const NONE         = 'NONE';        // not on the access list — no access
    public const VIEWER       = 'VIEWER';      // read-only
    public const REQUESTER    = 'REQUESTER';   // Tier 0–2 — may submit
    public const HOD          = 'HOD';
    public const REGIONAL_FIN = 'REGIONAL_FIN';
    public const COUNTRY_MD   = 'COUNTRY_MD';
    public const GROUP_CFO    = 'GROUP_CFO';
    public const SYSTEM_ADMIN = 'SYSTEM_ADMIN';

    /** Approval seniority. NONE is absent (no access); SYSTEM_ADMIN never approves. */
    private const RANK = [
        self::VIEWER       => 0,
        self::REQUESTER    => 0,
        self::HOD          => 1,
        self::REGIONAL_FIN => 2,
        self::COUNTRY_MD   => 3,
        self::GROUP_CFO    => 4,
        self::SYSTEM_ADMIN => -1,
    ];

    private const SUBMITTERS = [self::REQUESTER, self::HOD, self::REGIONAL_FIN, self::COUNTRY_MD, self::GROUP_CFO];
    private const TARGET_EDITORS = [self::REGIONAL_FIN, self::GROUP_CFO];

    public static function rank(string $role): int
    {
        return self::RANK[$role] ?? 0;
    }

    /** A recognised, access-granting role (anything but NONE / unknown). */
    public static function isValid(string $role): bool
    {
        return isset(self::RANK[$role]);
    }

    /** May the user open the app at all? */
    public static function canOpen(string $role): bool
    {
        return self::isValid($role);
    }

    /** May the user raise a capex request? (Tier 0–2 and above.) */
    public static function canSubmit(string $role): bool
    {
        return in_array($role, self::SUBMITTERS, true);
    }

    /** Is the user an approver at any band? */
    public static function canApprove(string $role): bool
    {
        return self::rank($role) >= 1;
    }

    /** May the user edit sales targets? (Finance / CFO.) */
    public static function canEditTargets(string $role): bool
    {
        return in_array($role, self::TARGET_EDITORS, true);
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
