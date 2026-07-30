<?php

declare(strict_types=1);

namespace Capex\Repo;

use Capex\Bitrix\Client;

/**
 * crm.item.* wrapper for the Capex Request SPA.
 * Field/entity codes come from config only — no string literals here.
 */
final class Requests
{
    /**
     * @param array<string,mixed> $fields  keyed by config field code
     */
    public function __construct(
        private readonly Client $client,
        private readonly int $entityTypeId,
        private readonly array $fields,
    ) {
    }

    /** @return array<int,array<string,mixed>> */
    public function inStage(string $stageId): array
    {
        $res = $this->client->call('crm.item.list', [
            'entityTypeId' => $this->entityTypeId,
            'filter'       => ['stageId' => $stageId],
        ]);

        return $res['result']['items'] ?? [];
    }

    /** @return array<string,mixed> */
    public function get(int $id): array
    {
        $res = $this->client->call('crm.item.get', [
            'entityTypeId' => $this->entityTypeId,
            'id'           => $id,
        ]);

        return $res['result']['item'] ?? [];
    }

    /** @param array<string,mixed> $fields */
    public function update(int $id, array $fields): void
    {
        $this->client->call('crm.item.update', [
            'entityTypeId' => $this->entityTypeId,
            'id'           => $id,
            'fields'       => $fields,
        ]);
    }
}
