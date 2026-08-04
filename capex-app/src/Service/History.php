<?php

declare(strict_types=1);

namespace Capex\Service;

use Capex\App;
use Capex\Domain\Money;
use Capex\Domain\Options;
use Capex\Domain\Roles;

/**
 * History of decided requests (approved + rejected). Lists them, builds a
 * submission→decision timeline from the audit log (falling back to the record's
 * own dates for requests created before the log existed), and lets high-access
 * users edit a past request — every edit appended to the audit log.
 */
final class History
{
    public function __construct(private readonly App $app)
    {
    }

    /**
     * Approved + rejected requests, newest decision first, with a one-line summary.
     * @return array<int,array<string,mixed>>
     */
    public function list(): array
    {
        $stages = $this->app->config['stages'];
        $presenter = new RequestPresenter($this->app);

        $rows = [];
        foreach (['approved', 'rejected'] as $key) {
            $stageId = $stages[$key] ?? '';
            if ($stageId === '') {
                continue;
            }
            foreach ($this->app->requests()->inStage($stageId) as $item) {
                $v = $presenter->present($item);
                $events = $this->timeline($v);
                $last = end($events) ?: [];
                $rows[] = [
                    'id'        => $v['id'],
                    'title'     => $v['title'],
                    'region'    => $v['region'],
                    'amount'    => $v['amountSgd'],
                    'stage'     => $v['stage'],
                    'decidedOn' => (string) ($last['ts'] ?? $v['dateApproval'] ?? ''),
                    'note'      => $v['approvalNote'] !== '' ? $v['approvalNote'] : (string) ($last['note'] ?? ''),
                ];
            }
        }

        usort($rows, fn ($a, $b) => strcmp((string) $b['decidedOn'], (string) $a['decidedOn']));

        return $rows;
    }

    /**
     * One request with its full timeline (user names + role labels resolved),
     * plus the option lists for editing. Null if the request doesn't exist.
     * @return array<string,mixed>|null
     */
    public function detail(int $requestId): ?array
    {
        $item = $this->app->requests()->get($requestId);
        if ($item === []) {
            return null;
        }

        $view = (new RequestPresenter($this->app))->present($item);
        $view['events']  = $this->resolveNames($this->timeline($view));
        $view['options'] = [
            'regions'      => Options::REGIONS,
            'costCentres'  => Options::COST_CENTRES,
            'categories'   => Options::CATEGORIES,
            'currencies'   => Options::CURRENCIES,
        ];

        return $view;
    }

    /**
     * Apply an edit from the History screen and log the field-level changes.
     * $edits is keyed by presenter keys (title, region, cost_centre, category,
     * amount_local, currency, justification, approval_note). Only provisioned
     * fields are written. Returns [ok, message].
     *
     * @param array<string,string> $edits
     * @return array{ok:bool,message:string}
     */
    public function saveEdit(string $role, int $userId, int $requestId, array $edits): array
    {
        if (!Roles::canEditHistory($role)) {
            return ['ok' => false, 'message' => 'Your role cannot edit history.'];
        }

        $item = $this->app->requests()->get($requestId);
        if ($item === []) {
            return ['ok' => false, 'message' => 'Request not found.'];
        }

        $f = $this->app->config['fields']['request'];
        $before = (new RequestPresenter($this->app))->present($item);

        // Map editable presenter keys -> [field code, before-value, money?].
        $editable = [
            'title'         => ['title',         $before['title'],        false],
            'region'        => [$f['region']        ?? '', $before['region'],       false],
            'cost_centre'   => [$f['cost_centre']   ?? '', $before['costCentre'],   false],
            'category'      => [$f['category']      ?? '', $before['category'],     false],
            'currency'      => [$f['currency']      ?? '', $before['currency'],     false],
            'justification' => [$f['justification'] ?? '', $before['justification'],false],
            'approval_note' => [$f['approval_note'] ?? '', $before['approvalNote'], false],
            'amount_local'  => [$f['amount_local']  ?? '', $before['amountLocal'],  true],
        ];

        $fields = [];
        $changes = [];
        foreach ($edits as $key => $raw) {
            if (!isset($editable[$key])) {
                continue;
            }
            [$code, $old, $isMoney] = $editable[$key];
            if ($code === '') {
                continue; // field not provisioned yet
            }
            $new = trim($raw);
            if ($isMoney) {
                $cents = Money::toCents(str_replace([',', ' '], '', $new));
                $newStore = Money::format($cents);
                $newDisplay = $newStore;
            } else {
                $newStore = $new;
                $newDisplay = $new;
            }
            if ((string) $newDisplay === (string) $old) {
                continue; // unchanged
            }
            $fields[$code] = $newStore;
            $changes[] = ['field' => $key, 'from' => (string) $old, 'to' => (string) $newDisplay];
        }

        // Recompute SGD when the local amount OR the currency changed.
        $amountChanged   = ($ac = (string) ($f['amount_local'] ?? '')) !== '' && isset($fields[$ac]);
        $currencyChanged = ($cc = (string) ($f['currency'] ?? '')) !== '' && isset($fields[$cc]);
        if (($amountChanged || $currencyChanged) && ($sgdCode = (string) ($f['amount_sgd'] ?? '')) !== '') {
            $currency = $edits['currency'] ?? $before['currency'];
            $localStr = $edits['amount_local'] ?? $before['amountLocal'];
            $localCents = Money::toCents(str_replace([',', ' '], '', (string) $localStr));
            $fields[$sgdCode] = Money::format($this->app->toSgd($localCents, (string) $currency));
        }

        if ($changes === []) {
            return ['ok' => false, 'message' => 'No changes to save.'];
        }

        $this->app->requests()->update($requestId, $fields);
        $this->app->auditStore()->append($requestId, [
            'ts'      => date('c'),
            'type'    => 'edited',
            'by'      => $userId,
            'byRole'  => $role,
            'note'    => '',
            'changes' => $changes,
        ]);

        return ['ok' => true, 'message' => sprintf('Request #%d updated (%d change(s) logged).', $requestId, count($changes))];
    }

    /**
     * Build the event timeline for a presented request: the audit log if present,
     * otherwise synthesised from the record's own dates so older requests still
     * show a submission and a decision.
     *
     * @param array<string,mixed> $view presented request
     * @return array<int,array<string,mixed>>
     */
    private function timeline(array $view): array
    {
        $events = $this->app->auditStore()->forRequest((int) $view['id']);

        $hasType = static function (array $events, string $type): bool {
            foreach ($events as $e) {
                if (($e['type'] ?? '') === $type) {
                    return true;
                }
            }
            return false;
        };

        $synth = [];
        if (!$hasType($events, 'submitted') && ($view['dateRequest'] ?? '') !== '') {
            $synth[] = ['ts' => $view['dateRequest'], 'type' => 'submitted', 'by' => null, 'byRole' => '', 'note' => ''];
        }
        // If nothing in the audit log records the decision, synthesise it from the stage + date.
        if (!$hasType($events, 'approved') && !$hasType($events, 'rejected')) {
            if ($view['stage'] === 'Approved' || $view['stage'] === 'Rejected') {
                $synth[] = [
                    'ts'     => (string) ($view['dateApproval'] ?? ''),
                    'type'   => strtolower($view['stage']),
                    'by'     => null,
                    'byRole' => '',
                    'note'   => (string) ($view['approvalNote'] ?? ''),
                ];
            }
        }

        $all = array_merge($synth, $events);
        usort($all, fn ($a, $b) => strcmp((string) ($a['ts'] ?? ''), (string) ($b['ts'] ?? '')));

        return $all;
    }

    /**
     * Resolve each event's actor id to a display name + role label.
     * @param array<int,array<string,mixed>> $events
     * @return array<int,array<string,mixed>>
     */
    private function resolveNames(array $events): array
    {
        $needNames = false;
        foreach ($events as $e) {
            if (!empty($e['by'])) {
                $needNames = true;
                break;
            }
        }
        $users = $needNames ? $this->app->portalUsers() : [];
        $labels = Roles::labels();

        return array_map(static function (array $e) use ($users, $labels): array {
            $by = $e['by'] ?? null;
            $e['byName'] = $by && isset($users[(int) $by]) ? $users[(int) $by] : ($by ? 'User #' . $by : 'System');
            $e['byRoleLabel'] = ($r = (string) ($e['byRole'] ?? '')) !== '' ? ($labels[$r] ?? $r) : '';
            return $e;
        }, $events);
    }
}
