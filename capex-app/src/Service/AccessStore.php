<?php

declare(strict_types=1);

namespace Capex\Service;

/**
 * The app-managed access list, stored as a small JSON file the app can write
 * (var/access.<env>.json). Seeded from config on first use, then owned by the
 * in-app Manage Access screen. Config remains the safety net: if this file is
 * ever missing it re-seeds, so the bootstrap admins can always get back in.
 */
final class AccessStore
{
    public function __construct(private readonly string $path)
    {
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /** @return array<int,string> userId => role */
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
        foreach ($data as $k => $v) {
            $out[(int) $k] = (string) $v;
        }

        return $out;
    }

    /** @param array<int,string> $map */
    public function save(array $map): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        ksort($map);
        file_put_contents($this->path, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
