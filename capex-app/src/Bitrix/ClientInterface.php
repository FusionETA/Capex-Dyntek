<?php

declare(strict_types=1);

namespace Capex\Bitrix;

/**
 * Seam over the Bitrix REST client so the Repo + Service layers can be unit
 * tested with a fake, without a network round-trip.
 */
interface ClientInterface
{
    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function call(string $method, array $params = []): array;

    /**
     * @param array<string,string> $commands
     * @return array<string,mixed>
     */
    public function batch(array $commands, bool $halt = false): array;
}
