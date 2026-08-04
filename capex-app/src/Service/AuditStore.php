<?php

declare(strict_types=1);

namespace Capex\Service;

/**
 * Append-only audit log for Capex Requests, stored as a small JSON file the app
 * can write (var/audit.<env>.json). Keyed by request id -> list of events in the
 * order they happened. This is the source of truth for the History timeline and
 * change-log, since Bitrix's own field-level history isn't cleanly exposed over
 * REST. The Bitrix records stay authoritative for the data itself; this records
 * WHO did WHAT and WHEN through the app.
 *
 * Event shape (all optional except type/ts):
 *   ['ts'=>ISO8601, 'type'=>'submitted|approved|rejected|edited',
 *    'by'=>userId, 'byRole'=>role, 'note'=>string, 'changes'=>[[field,from,to],...]]
 */
final class AuditStore
{
    public function __construct(private readonly string $path)
    {
    }

    /** @return array<int,array<int,array<string,mixed>>> requestId => events */
    public function all(): array
    {
        if (!is_file($this->path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($this->path), true);
        if (!is_array($data)) {
            return [];
        }

        $out = [];
        foreach ($data as $id => $events) {
            if (is_array($events)) {
                $out[(int) $id] = array_values($events);
            }
        }

        return $out;
    }

    /** @return array<int,array<string,mixed>> events for one request, oldest first */
    public function forRequest(int $requestId): array
    {
        return $this->all()[$requestId] ?? [];
    }

    /**
     * Append one event to a request's log and persist.
     * @param array<string,mixed> $event
     */
    public function append(int $requestId, array $event): void
    {
        $all = $this->all();
        $all[$requestId][] = $event;
        $this->write($all);
    }

    /** @param array<int,array<int,array<string,mixed>>> $all */
    private function write(array $all): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        ksort($all);
        file_put_contents($this->path, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
