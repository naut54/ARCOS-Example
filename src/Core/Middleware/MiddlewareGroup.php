<?php

declare(strict_types=1);

namespace Arcos\Core\Middleware;

use LogicException;

final class MiddlewareGroup
{
    private function __construct(
        public readonly string $name,
        public readonly array  $links = [], // MiddlewareLink[]
    ) {}

    public static function define(string $name, array $links): static
    {
        $validated = [];

        foreach ($links as $link) {
            $link = $link instanceof MiddlewareLink
                ? $link
                : new MiddlewareLink($link);

            if (!$link->canHaveGroup) {
                throw new LogicException(
                    "Middleware [{$link->middleware}] has canHaveGroup=false and cannot be added to group [{$name}]."
                );
            }

            $validated[] = $link;
        }

        return new static($name, $validated);
    }

    public static function ref(string $name): static
    {
        return new static($name);
    }

    public static function skip(string $name): static
    {
        return new static($name);
    }

    public function isRef(): bool
    {
        return $this->links === [];
    }
}