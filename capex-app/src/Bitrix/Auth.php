<?php

declare(strict_types=1);

namespace Capex\Bitrix;

/**
 * Local app OAuth 2.0 token store + refresh.
 *
 * Stores access_token, refresh_token, expires, member_id in var/tokens.sqlite.
 * Any request whose member_id isn't the installed portal must be rejected by
 * the caller (see build plan §4.1).
 *
 * TODO(M2): implement the SQLite-backed store and the refresh round-trip.
 */
final class Auth
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $tokenStorePath, // var/tokens.sqlite
    ) {
    }

    /** Current access token, refreshing first if it has expired. */
    public function accessToken(): string
    {
        throw new \LogicException('Auth::accessToken not implemented (M2).');
    }

    /** Exchange the refresh_token for a new access_token; persist and return it. */
    public function refresh(): string
    {
        throw new \LogicException('Auth::refresh not implemented (M2).');
    }

    /** Persist the token bundle Bitrix posts on install/open. */
    public function store(string $accessToken, string $refreshToken, int $expires, string $memberId): void
    {
        throw new \LogicException('Auth::store not implemented (M2).');
    }

    /** True if the caller's member_id matches the installed portal. */
    public function isInstalledPortal(string $memberId): bool
    {
        throw new \LogicException('Auth::isInstalledPortal not implemented (M2).');
    }
}
