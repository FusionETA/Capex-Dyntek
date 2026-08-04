<?php

declare(strict_types=1);

namespace Capex\Service;

/**
 * The app-managed delegation-of-authority bands, stored as a small JSON file the
 * app can write (var/bands.<env>.json). Seeded from config['authority_bands'] on
 * first use, then owned by the Manage Access screen. Stored as an ordered list of
 * [ceilingCents, role] pairs so the ascending order is preserved explicitly.
 */
final class BandStore
{
    public function __construct(private readonly string $path)
    {
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /** @return array<int,array{0:int,1:string}> ordered [ceilingCents, role] pairs */
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
        foreach ($data as $pair) {
            if (is_array($pair) && isset($pair[0], $pair[1])) {
                $out[] = [(int) $pair[0], (string) $pair[1]];
            }
        }

        return $out;
    }

    /** @param array<int,array{0:int,1:string}> $pairs */
    public function save(array $pairs): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        // Persist ascending by ceiling so routing is well-ordered regardless of input.
        usort($pairs, static fn ($a, $b) => $a[0] <=> $b[0]);
        file_put_contents($this->path, json_encode(array_values($pairs), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
