<?php

declare(strict_types=1);

namespace Capex\Repo;

use Capex\Bitrix\Client;
use Capex\Domain\Envelope;

/**
 * crm.item.* wrapper for the Budget Envelope SPA.
 * Uniqueness on region + FY is enforced HERE (check before create), not by the platform.
 */
final class Envelopes
{
    /** @param array<string,mixed> $fields */
    public function __construct(
        private readonly Client $client,
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

    /** Persist app-derived committed/spent totals back onto the envelope. */
    public function writeTotals(int $id, int $committedSgd, int $spentSgd): void
    {
        $this->client->call('crm.item.update', [
            'entityTypeId' => $this->entityTypeId,
            'id'           => $id,
            'fields'       => [
                $this->fields['committed_sgd'] => $committedSgd,
                $this->fields['spent_sgd']     => $spentSgd,
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
            approvedSgd:  (int) ($item[$this->fields['approved_sgd']] ?? 0),
            committedSgd: (int) ($item[$this->fields['committed_sgd']] ?? 0),
            spentSgd:     (int) ($item[$this->fields['spent_sgd']] ?? 0),
            fxRateToSgd:  (float) ($item[$this->fields['fx_rate_to_sgd']] ?? 1.0),
            status:       (string) ($item[$this->fields['status']] ?? 'draft'),
        );
    }
}
