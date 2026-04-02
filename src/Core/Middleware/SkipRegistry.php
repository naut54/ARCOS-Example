<?php

declare(strict_types=1);

namespace Arcos\Core\Middleware;

class SkipRegistry
{
    private static ?self $instance = null;
    private array $skipped = []; // class-name strings

    private function __construct() {}

    public static function current(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public static function reset(): void
    {
        static::$instance = null;
    }

    public function skip(MiddlewareLink|MiddlewareGroup $target): void
    {
        if ($target instanceof MiddlewareLink) {
            $this->skipped[] = $target->middleware;
            return;
        }

        // MiddlewareGroup ref — resolve all class names from the group
        foreach ($target->links as $link) {
            $this->skipped[] = $link->middleware;
        }
    }

    public function shouldSkip(string $middlewareClass): bool
    {
        return in_array($middlewareClass, $this->skipped, strict: true);
    }
}