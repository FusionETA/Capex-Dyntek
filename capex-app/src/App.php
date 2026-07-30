<?php

declare(strict_types=1);

namespace Capex;

use Capex\Bitrix\Auth;
use Capex\Bitrix\Client;

/**
 * Tiny composition root. Loads config once and wires Auth + Client so the entry
 * points (index.php, install.php) don't repeat construction. No DI container,
 * no framework — just enough to keep field codes and secrets in one place.
 */
final class App
{
    /** @param array<string,mixed> $config */
    private function __construct(
        public readonly array $config,
        public readonly Auth $auth,
        public readonly Client $client,
    ) {
    }

    public static function boot(?string $configPath = null): self
    {
        $configPath ??= __DIR__ . '/../config/app.php';
        /** @var array<string,mixed> $config */
        $config = require $configPath;

        $auth = new Auth(
            $config['oauth']['client_id'],
            $config['oauth']['client_secret'],
            __DIR__ . '/../var/tokens.sqlite',
        );

        $client = new Client($auth, $config['oauth']['portal_domain']);

        return new self($config, $auth, $client);
    }

    /**
     * Verify an inbound request belongs to the installed portal, then pin the
     * active member. Call this in every handler that touches Bitrix on behalf of
     * a caller. Returns false when the member_id is missing or unknown.
     */
    public function verifyCaller(?string $memberId): bool
    {
        if ($memberId === null || $memberId === '' || !$this->auth->isInstalledPortal($memberId)) {
            return false;
        }

        $this->auth->useMember($memberId);

        return true;
    }
}
