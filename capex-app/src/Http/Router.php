<?php

declare(strict_types=1);

namespace Capex\Http;

/**
 * Minimal path-based router. No framework — maps a path to a handler callable.
 * Every handler re-checks the caller's Bitrix24 rights server-side; never trust
 * a hidden button (build plan §4.4).
 */
final class Router
{
    /** @var array<string,callable> */
    private array $routes = [];

    public function add(string $path, callable $handler): void
    {
        $this->routes[$path] = $handler;
    }

    public function dispatch(string $path): void
    {
        $handler = $this->routes[$path] ?? null;

        if ($handler === null) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $handler();
    }
}
