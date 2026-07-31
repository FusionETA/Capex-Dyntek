<?php

declare(strict_types=1);

namespace Capex\Repo;

use Capex\Bitrix\ClientInterface;
use Capex\Domain\Money;

/**
 * crm.item.* wrapper for the Capex Request SPA.
 * Field/entity codes come from config only — no string literals here.
 */
final class Requests
{
    /**
     * @param array<string,string> $fields keyed by config field code
     */
    public function __construct(
        private readonly ClientInterface $client,
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

    /**
     * Create a Capex Request. Returns the new item id.
     * @param array<string,mixed> $fields keyed by real Bitrix field code
     */
    public function create(array $fields): int
    {
        $res = $this->client->call('crm.item.add', [
            'entityTypeId' => $this->entityTypeId,
            'fields'       => $fields,
        ]);

        return (int) ($res['result']['item']['id'] ?? 0);
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
