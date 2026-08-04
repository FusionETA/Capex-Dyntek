<?php

declare(strict_types=1);

namespace Capex\Service;

use Capex\App;
use Capex\Domain\Money;

/**
 * Turns a raw crm.item Capex Request into a display-ready model shared by the
 * approval detail and history screens. Centralises field-code lookups (all via
 * config, never literals) and the messy attachment-shape handling.
 */
final class RequestPresenter
{
    /** @var array<string,string> */
    private array $f;

    public function __construct(private readonly App $app)
    {
        $this->f = $this->app->config['fields']['request'];
    }

    /** A field's REST code, or '' if it hasn't been provisioned/discovered yet. */
    public function code(string $key): string
    {
        return (string) ($this->f[$key] ?? '');
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    public function present(array $item): array
    {
        $get = fn (string $key): mixed => ($c = $this->code($key)) !== '' ? ($item[$c] ?? null) : null;
        $stages = $this->app->config['stages'];
        $stageId = (string) ($item['stageId'] ?? '');

        $stageLabel = match ($stageId) {
            ($stages['approved'] ?? null)  => 'Approved',
            ($stages['rejected'] ?? null)  => 'Rejected',
            ($stages['submitted'] ?? null) => 'Submitted',
            ($stages['draft'] ?? null)     => 'Draft',
            default                        => $stageId,
        };

        return [
            'id'           => (int) ($item['id'] ?? 0),
            'title'        => (string) ($item['title'] ?? 'Untitled'),
            'region'       => (string) ($get('region') ?? '—'),
            'category'     => (string) ($get('category') ?? ''),
            'costCentre'   => (string) ($get('cost_centre') ?? ''),
            'pic'          => (string) ($get('pic') ?? ''),
            'timeline'     => (string) ($get('timeline') ?? ''),
            'currency'     => (string) ($get('currency') ?? ''),
            'amountLocal'  => Money::format(Money::fieldToCents($get('amount_local'))),
            'amountSgd'    => Money::format(Money::fieldToCents($get('amount_sgd'))),
            'payback'      => (string) ($get('payback_months') ?? ''),
            'justification'=> (string) ($get('justification') ?? ''),
            'approvalNote' => (string) ($get('approval_note') ?? ''),
            'dateRequest'  => (string) ($get('date_request') ?? ''),
            'dateApproval' => (string) ($get('date_approval') ?? ''),
            'stageId'      => $stageId,
            'stage'        => $stageLabel,
            'attachment'   => $this->attachment($get('attachment')),
        ];
    }

    /**
     * Normalise a file user-field value to ['name'=>, 'href'=>] or null. Bitrix
     * returns file fields in a few shapes across versions (object with url/
     * urlMachine/downloadUrl, a list of those, or a bare id) — handle the common
     * ones and degrade gracefully.
     *
     * @return array{name:string,href:string}|null
     */
    private function attachment(mixed $value): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        // A list of files — take the first.
        if (is_array($value) && array_is_list($value) && isset($value[0]) && is_array($value[0])) {
            $value = $value[0];
        }

        if (is_array($value)) {
            $href = (string) ($value['downloadUrl'] ?? $value['urlMachine'] ?? $value['url'] ?? '');
            $name = (string) ($value['name'] ?? '');
            if ($name === '' && $href !== '') {
                $name = basename(parse_url($href, PHP_URL_PATH) ?: 'attachment');
            }
            if ($href === '') {
                return null;
            }

            return ['name' => $name !== '' ? $name : 'Attachment', 'href' => $this->absolute($href)];
        }

        // Bare id — we can't build a reliable link, so just note that a file exists.
        return ['name' => 'Attached file', 'href' => ''];
    }

    /** Make a Bitrix-relative file URL absolute against the portal domain. */
    private function absolute(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        $domain = (string) ($this->app->config['oauth']['portal_domain'] ?? '');

        return $domain !== '' ? 'https://' . $domain . '/' . ltrim($url, '/') : $url;
    }
}
