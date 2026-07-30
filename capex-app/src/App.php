<?php

declare(strict_types=1);

namespace Capex;

use Capex\Bitrix\Auth;
use Capex\Bitrix\Client;
use Capex\Repo\Envelopes;
use Capex\Repo\Requests;
use Capex\Repo\Targets;

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
        public readonly string $env,
        public readonly string $generatedPath,
    ) {
    }

    /**
     * Boot for a named environment (test / prod / …). When $env is null it is
     * resolved from the APP_ENV env var, defaulting to 'prod'. Each environment
     * gets its own config file, token store and generated codes, so the Fusion
     * test portal and the Dyntek prod portal never share state.
     */
    public static function boot(?string $env = null): self
    {
        $env ??= self::resolveEnv();
        $paths = self::paths(__DIR__ . '/../config', __DIR__ . '/../var', $env);

        /** @var array<string,mixed> $config */
        $config = require $paths['config'];

        // Overlay entity ids + discovered field/stage codes from provisioning, if present.
        if (is_file($paths['generated'])) {
            /** @var array<string,mixed> $generated */
            $generated = require $paths['generated'];
            $config = self::mergeGenerated($config, $generated);
        }

        $auth = new Auth(
            $config['oauth']['client_id'],
            $config['oauth']['client_secret'],
            $paths['tokens'],
        );

        $client = new Client($auth, $config['oauth']['portal_domain']);

        return new self($config, $auth, $client, $env, $paths['generated']);
    }

    /**
     * Resolve the active environment from APP_ENV (env var or SetEnv), sanitised
     * to a safe slug so it can't escape the config/var directories. Defaults to
     * 'prod' — the safe assumption for an un-flagged deployment.
     */
    public static function resolveEnv(): string
    {
        $raw = getenv('APP_ENV');
        if ($raw === false || $raw === '') {
            $raw = (string) ($_SERVER['APP_ENV'] ?? '');
        }

        $slug = strtolower(preg_replace('/[^A-Za-z0-9_]/', '', $raw) ?? '');

        return $slug !== '' ? $slug : 'prod';
    }

    /**
     * Resolve the per-environment file paths. If an env-specific config exists
     * (app.<env>.php) its sibling token store and generated file are used;
     * otherwise the legacy single-env names (app.php / tokens.sqlite) apply, so
     * existing single-portal setups keep working.
     *
     * @return array{config:string,generated:string,tokens:string}
     */
    public static function paths(string $configDir, string $varDir, string $env): array
    {
        $envConfig = "{$configDir}/app.{$env}.php";
        if (is_file($envConfig)) {
            return [
                'config'    => $envConfig,
                'generated' => "{$configDir}/generated.{$env}.php",
                'tokens'    => "{$varDir}/tokens.{$env}.sqlite",
            ];
        }

        return [
            'config'    => "{$configDir}/app.php",
            'generated' => "{$configDir}/generated.php",
            'tokens'    => "{$varDir}/tokens.sqlite",
        ];
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

    public function requests(): Requests
    {
        return new Requests($this->client, (int) $this->config['entities']['request'], $this->config['fields']['request']);
    }

    public function envelopes(): Envelopes
    {
        return new Envelopes($this->client, (int) $this->config['entities']['envelope'], $this->config['fields']['envelope']);
    }

    public function targets(): Targets
    {
        return new Targets($this->client, (int) $this->config['entities']['target'], $this->config['fields']['target']);
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
