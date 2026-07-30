<?php

declare(strict_types=1);

namespace Capex\Bitrix;

use PDO;

/**
 * Local app OAuth 2.0 token store + refresh.
 *
 * Stores access_token, refresh_token, expires (absolute unix ts) and member_id
 * in var/tokens.sqlite. A local app is installed against exactly one portal, so
 * normally there is a single row; requests whose member_id isn't the installed
 * portal must be rejected (see isInstalledPortal / build plan §4.1).
 *
 * The HTTP refresh call is injected as a callable so the persistence + expiry
 * logic can be tested without a network round-trip.
 */
final class Auth
{
    /** Refresh a token slightly before it actually expires, to avoid a race. */
    private const EXPIRY_SKEW = 60;

    private const OAUTH_ENDPOINT = 'https://oauth.bitrix.info/oauth/token/';

    private ?PDO $pdo = null;

    private ?string $activeMember = null;

    /** @var callable(string $refreshToken):array<string,mixed> */
    private $refresher;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $tokenStorePath, // var/tokens.sqlite
        ?callable $refresher = null,
    ) {
        $this->refresher = $refresher ?? fn (string $rt): array => $this->httpRefresh($rt);
    }

    /**
     * Pin subsequent calls to a specific portal. Throws if that portal isn't the
     * one this app was installed against.
     */
    public function useMember(string $memberId): void
    {
        if (!$this->isInstalledPortal($memberId)) {
            throw new \RuntimeException("Unknown member_id: {$memberId}");
        }
        $this->activeMember = $memberId;
    }

    /** Current access token, refreshing first if it has expired (or is about to). */
    public function accessToken(): string
    {
        $row = $this->currentRow();
        if ($row === null) {
            throw new \RuntimeException('No stored token — app not installed?');
        }

        if ((int) $row['expires'] <= time() + self::EXPIRY_SKEW) {
            return $this->refresh();
        }

        return (string) $row['access_token'];
    }

    /** Exchange the refresh_token for a fresh bundle; persist and return the new access token. */
    public function refresh(): string
    {
        $row = $this->currentRow();
        if ($row === null) {
            throw new \RuntimeException('No stored token to refresh.');
        }

        $bundle = ($this->refresher)((string) $row['refresh_token']);

        if (empty($bundle['access_token']) || empty($bundle['refresh_token'])) {
            throw new \RuntimeException('Refresh did not return a valid token bundle.');
        }

        $memberId = (string) ($bundle['member_id'] ?? $row['member_id']);
        $expires = isset($bundle['expires'])
            ? (int) $bundle['expires']
            : time() + (int) ($bundle['expires_in'] ?? 3600);

        $this->store(
            (string) $bundle['access_token'],
            (string) $bundle['refresh_token'],
            $expires,
            $memberId,
        );

        return (string) $bundle['access_token'];
    }

    /** Persist the token bundle Bitrix posts on install/open. $expires is an absolute unix ts. */
    public function store(string $accessToken, string $refreshToken, int $expires, string $memberId): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO tokens (member_id, access_token, refresh_token, expires, updated_at)
             VALUES (:m, :a, :r, :e, :u)
             ON CONFLICT(member_id) DO UPDATE SET
                access_token = excluded.access_token,
                refresh_token = excluded.refresh_token,
                expires = excluded.expires,
                updated_at = excluded.updated_at'
        );

        $stmt->execute([
            ':m' => $memberId,
            ':a' => $accessToken,
            ':r' => $refreshToken,
            ':e' => $expires,
            ':u' => time(),
        ]);

        $this->activeMember = $memberId;
    }

    /** True if the caller's member_id matches an installed portal. */
    public function isInstalledPortal(string $memberId): bool
    {
        $stmt = $this->pdo()->prepare('SELECT 1 FROM tokens WHERE member_id = :m LIMIT 1');
        $stmt->execute([':m' => $memberId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * The active portal's token row, or the sole row for a single-portal install.
     *
     * @return array<string,mixed>|null
     */
    private function currentRow(): ?array
    {
        if ($this->activeMember !== null) {
            $stmt = $this->pdo()->prepare('SELECT * FROM tokens WHERE member_id = :m LIMIT 1');
            $stmt->execute([':m' => $this->activeMember]);
        } else {
            // Single-portal local app: fall back to the most recently updated row.
            $stmt = $this->pdo()->query('SELECT * FROM tokens ORDER BY updated_at DESC LIMIT 1');
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return array<string,mixed> */
    private function httpRefresh(string $refreshToken): array
    {
        $query = http_build_query([
            'grant_type'    => 'refresh_token',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
        ]);

        $ch = curl_init(self::OAUTH_ENDPOINT . '?' . $query);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            throw new \RuntimeException('OAuth refresh cURL error: ' . curl_error($ch));
        }

        /** @var array<string,mixed> $decoded */
        $decoded = json_decode((string) $raw, true) ?? [];

        if (isset($decoded['error'])) {
            throw new \RuntimeException(
                sprintf('OAuth refresh failed: %s %s', $decoded['error'], $decoded['error_description'] ?? '')
            );
        }

        return $decoded;
    }

    private function pdo(): PDO
    {
        if ($this->pdo === null) {
            $dir = dirname($this->tokenStorePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0770, true);
            }

            $this->pdo = new PDO('sqlite:' . $this->tokenStorePath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS tokens (
                    member_id     TEXT PRIMARY KEY,
                    access_token  TEXT NOT NULL,
                    refresh_token TEXT NOT NULL,
                    expires       INTEGER NOT NULL,
                    updated_at    INTEGER NOT NULL
                )'
            );
        }

        return $this->pdo;
    }
}
