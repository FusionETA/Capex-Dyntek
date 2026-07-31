<?php

declare(strict_types=1);

namespace Capex\Repo;

use Capex\Bitrix\ClientInterface;

/**
 * crm.item.* wrapper for the Sales Target SPA. Finance (Carol) maintains the
 * figures — new target and current met are typed in, nothing is derived.
 */
final class Targets
{
    /** @param array<string,string> $fields */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly int $entityTypeId,
        private readonly array $fields,
    ) {
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        $res = $this->client->call('crm.item.list', ['entityTypeId' => $this->entityTypeId]);

        return $res['result']['items'] ?? [];
    }

    /** @return array<string,mixed>|null */
    public function get(int $id): ?array
    {
        $res = $this->client->call('crm.item.get', [
            'entityTypeId' => $this->entityTypeId,
            'id'           => $id,
        ]);

        return $res['result']['item'] ?? null;
    }

    /** @param array<string,mixed> $fields keyed by real Bitrix field code */
    public function update(int $id, array $fields): void
    {
        $this->client->call('crm.item.update', [
            'entityTypeId' => $this->entityTypeId,
            'id'           => $id,
            'fields'       => $fields,
        ]);
    }
}
