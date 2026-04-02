<?php

declare(strict_types=1);

namespace Arcos\Core\Middleware;

final class MiddlewareRemove
{
    private function __construct(
        public readonly string $middleware,
    ) {}

    public static function ref(string $middlewareClass): static
    {
        return new static($middlewareClass);
    }
}