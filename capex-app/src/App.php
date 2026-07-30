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

        // Overlay entity ids + discovered field/stage codes from provisioning, if present.
        $generatedPath = dirname($configPath) . '/generated.php';
        if (is_file($generatedPath)) {
            /** @var array<string,mixed> $generated */
            $generated = require $generatedPath;
            $config = self::mergeGenerated($config, $generated);
        }

        $auth = new Auth(
            $config['oauth']['client_id'],
            $config['oauth']['client_secret'],
            __DIR__ . '/../var/tokens.sqlite',
        );

        $client = new Client($auth, $config['oauth']['portal_domain']);

        return new self($config, $auth, $client);
    }

    /**
     * Overlay provisioning output onto the base config. Discovered entity ids,
     * field codes and stage ids win over the hand-written placeholders; anything
     * provisioning didn't touch (oauth, authority_bands, current_fy) is preserved.
     *
     * @param array<string,mixed> $config
     * @param array{entities?:array<string,int>,fields?:array<string,array<string,string>>,stages?:array<string,string>} $generated
     * @return array<string,mixed>
     */
    private static function mergeGenerated(array $config, array $generated): array
    {
        if (!empty($generated['entities'])) {
            $config['entities'] = array_merge($config['entities'] ?? [], $generated['entities']);
        }
        if (!empty($generated['stages'])) {
            $config['stages'] = array_merge($config['stages'] ?? [], $generated['stages']);
        }
        if (!empty($generated['fields'])) {
            foreach ($generated['fields'] as $entity => $codes) {
                $config['fields'][$entity] = array_merge($config['fields'][$entity] ?? [], $codes);
            }
        }

        return $config;
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
