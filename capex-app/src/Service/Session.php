<?php

declare(strict_types=1);

namespace Capex\Service;

/**
 * Tamper-proof, stateless viewer identity for the iframe app. The current user is
 * resolved once from Bitrix's placement auth, then carried through navigation as a
 * signed token — because the app acts with the admin service token, a spoofable
 * user id would let anyone approve. Token = uid.role.exp.hmac (base64url parts).
 *
 * Stateless (no cookies — third-party cookies are unreliable inside the Bitrix
 * iframe) and HMAC-signed with the app's client_secret, so it can't be forged.
 */
final class Session
{
    private const TTL = 3600; // seconds; re-issued on each request

    public function __construct(private readonly string $secret)
    {
    }

    public function issue(int $userId, string $role, int $now): string
    {
        $exp = $now + self::TTL;
        $payload = $userId . '.' . $role . '.' . $exp;

        return $payload . '.' . $this->sign($payload);
    }

    /**
     * Verify a token. Returns ['id'=>int,'role'=>string] or null if invalid/expired.
     * @return array{id:int,role:string}|null
     */
    public function verify(string $token, int $now): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 4) {
            return null;
        }
        [$uid, $role, $exp, $sig] = $parts;

        $payload = $uid . '.' . $role . '.' . $exp;
        if (!hash_equals($this->sign($payload), $sig)) {
            return null;
        }
        if ((int) $exp < $now) {
            return null;
        }

        return ['id' => (int) $uid, 'role' => $role];
    }

    private function sign(string $payload): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $this->secret, true)), '+/', '-_'), '=');
    }
}
