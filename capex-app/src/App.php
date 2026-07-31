<?php

declare(strict_types=1);

namespace Capex;

use Capex\Bitrix\Auth;
use Capex\Bitrix\Client;
use Capex\Domain\Money;
use Capex\Domain\Roles;
use Capex\Repo\Requests;
use Capex\Repo\Targets;
use Capex\Service\Session;

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

    public function targets(): Targets
    {
        return new Targets($this->client, (int) $this->config['entities']['target'], $this->config['fields']['target']);
    }

    /** Convert a local amount (cents) to SGD cents using the configured FX rate. */
    public function toSgd(int $localCents, string $currency): int
    {
        $rate = (float) ($this->config['fx_rates'][$currency] ?? 1.0);

        return Money::toSGD($localCents, $rate);
    }

    private function session(): Session
    {
        return new Session((string) ($this->config['oauth']['client_secret'] ?? ''));
    }

    /**
     * Resolve the viewing user + their role, and a fresh signed token to carry
     * through navigation. Identity comes from Bitrix's placement auth (AUTH_ID →
     * user.current) on first load, then from the signed token on later requests.
     * Anonymous (id 0, Requester, no token) when neither is present.
     *
     * @return array{id:int,role:string,token:string}
     */
    public function resolveUser(): array
    {
        $now = time();
        $session = $this->session();

        $tok = (string) ($_REQUEST['utok'] ?? '');
        if ($tok !== '') {
            $u = $session->verify($tok, $now);
            if ($u !== null) {
                return ['id' => $u['id'], 'role' => $u['role'], 'token' => $session->issue($u['id'], $u['role'], $now)];
            }
        }

        $authId = (string) ($_REQUEST['AUTH_ID'] ?? '');
        if ($authId !== '') {
            $uid = $this->userIdFromToken($authId);
            if ($uid > 0) {
                $role = $this->roleFor($uid);
                return ['id' => $uid, 'role' => $role, 'token' => $session->issue($uid, $role, $now)];
            }
        }

        return ['id' => 0, 'role' => Roles::REQUESTER, 'token' => ''];
    }

    /** Map a Bitrix user id to a role from config; everyone else is a Requester. */
    public function roleFor(int $userId): string
    {
        $role = (string) ($this->config['roles'][$userId] ?? Roles::REQUESTER);

        return Roles::isValid($role) ? $role : Roles::REQUESTER;
    }

    /** Resolve a Bitrix user id from an access token via user.current. 0 on failure. */
    private function userIdFromToken(string $accessToken): int
    {
        $url = sprintf('https://%s/rest/user.current', $this->config['oauth']['portal_domain']);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['auth' => $accessToken]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            return 0;
        }

        $decoded = json_decode((string) $raw, true);

        return (int) ($decoded['result']['ID'] ?? 0);
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
