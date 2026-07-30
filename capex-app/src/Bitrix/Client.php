<?php

declare(strict_types=1);

namespace Capex\Bitrix;

/**
 * Thin REST wrapper over the Bitrix24 API. Plain cURL — no Composer packages
 * required. Auto-refreshes on expired_token via Auth.
 */
final class Client
{
    public function __construct(
        private readonly Auth $auth,
        private readonly string $portalDomain,
    ) {
    }

    /**
     * Call a single REST method.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function call(string $method, array $params = []): array
    {
        $token = $this->auth->accessToken();
        $url = sprintf('https://%s/rest/%s', $this->portalDomain, $method);

        $response = $this->post($url, $params + ['auth' => $token]);

        if (($response['error'] ?? null) === 'expired_token') {
            $token = $this->auth->refresh();
            $response = $this->post($url, $params + ['auth' => $token]);
        }

        if (isset($response['error'])) {
            throw new \RuntimeException(
                sprintf('Bitrix %s: %s', $response['error'], $response['error_description'] ?? '')
            );
        }

        return $response;
    }

    /**
     * Batch up to 50 calls in one HTTP round-trip.
     *
     * @param array<string,string> $commands  [ key => "method?querystring" ]
     * @return array<string,mixed>
     */
    public function batch(array $commands, bool $halt = false): array
    {
        $result = $this->call('batch', ['halt' => $halt ? 1 : 0, 'cmd' => $commands]);

        return $result['result'] ?? [];
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function post(string $url, array $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("cURL error: {$err}");
        }
        curl_close($ch);

        /** @var array<string,mixed> $decoded */
        $decoded = json_decode((string) $raw, true) ?? [];

        return $decoded;
    }
}
