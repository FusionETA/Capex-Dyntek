<?php

declare(strict_types=1);

namespace Capex\Repo;

use Capex\Bitrix\Client;

/**
 * crm.item.* wrapper for the Sales Target SPA. Finance-maintained, read-only to others.
 */
final class Targets
{
    /** @param array<string,mixed> $fields */
    public function __construct(
        private readonly Client $client,
        private readonly int $entityTypeId,
        private readonly array $fields,
    ) {
    }

    /** @return array<int,array<string,mixed>> */
    public function forRegion(string $region): array
    {
        $res = $this->client->call('crm.item.list', [
            'entityTypeId' => $this->entityTypeId,
            'filter'       => [$this->fields['region'] => $region],
        ]);

        return $res['result']['items'] ?? [];
    }
}
