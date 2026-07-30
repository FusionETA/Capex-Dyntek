<?php

declare(strict_types=1);

namespace Capex\Repo;

use Capex\Bitrix\ClientInterface;
use Capex\Domain\Envelope;
use Capex\Domain\Money;

/**
 * crm.item.* wrapper for the Budget Envelope SPA.
 * Uniqueness on region + FY is enforced HERE (check before create), not by the platform.
 * All money fields are parsed to/from integer cents at this boundary.
 */
final class Envelopes
{
    /** @param array<string,string> $fields */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly int $entityTypeId,
        private readonly array $fields,
    ) {
    }

    public function find(string $region, int $fy): ?Envelope
    {
        $res = $this->client->call('crm.item.list', [
            'entityTypeId' => $this->entityTypeId,
            'filter'       => [
                $this->fields['region'] => $region,
                $this->fields['fy']     => $fy,
            ],
        ]);

        $item = $res['result']['items'][0] ?? null;

        return $item ? $this->hydrate($item) : null;
    }

    public function getById(int $id): ?Envelope
    {
        $res = $this->client->call('crm.item.get', [
            'entityTypeId' => $this->entityTypeId,
            'id'           => $id,
        ]);

        $item = $res['result']['item'] ?? null;

        return $item ? $this->hydrate($item) : null;
    }

    /** Persist app-derived committed/spent totals (in cents) back onto the envelope. */
    public function writeTotals(int $id, int $committedSgdCents, int $spentSgdCents): void
    {
        $this->client->call('crm.item.update', [
            'entityTypeId' => $this->entityTypeId,
            'id'           => $id,
            'fields'       => [
                $this->fields['committed_sgd'] => Money::format($committedSgdCents),
                $this->fields['spent_sgd']     => Money::format($spentSgdCents),
            ],
        ]);
    }

    /** @param array<string,mixed> $item */
    private function hydrate(array $item): Envelope
    {
        return new Envelope(
            id:           (int) $item['id'],
            region:       (string) ($item[$this->fields['region']] ?? ''),
            fy:           (int) ($item[$this->fields['fy']] ?? 0),
            approvedSgd:  Money::fieldToCents($item[$this->fields['approved_sgd']] ?? null),
            committedSgd: Money::fieldToCents($item[$this->fields['committed_sgd']] ?? null),
            spentSgd:     Money::fieldToCents($item[$this->fields['spent_sgd']] ?? null),
            fxRateToSgd:  (float) ($item[$this->fields['fx_rate_to_sgd']] ?? 1.0),
            status:       (string) ($item[$this->fields['status']] ?? 'draft'),
        );
    }
}
