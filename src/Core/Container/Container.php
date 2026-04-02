<?php

declare(strict_types=1);

namespace Arcos\Core\Container;

use Closure;
use RuntimeException;

class Container
{
    private array $bindings = [];
    private array $resolved = [];

    public function bind(string $abstract, Closure $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    public function singleton(string $abstract, Closure $factory): void
    {
        $this->bindings[$abstract] = function () use ($abstract, $factory) {
            if (!isset($this->resolved[$abstract])) {
                $this->resolved[$abstract] = $factory($this);
            }

            return $this->resolved[$abstract];
        };
    }

    public function make(string $abstract): mixed
    {
        if (!isset($this->bindings[$abstract])) {
            throw new RuntimeException(
                "No binding found for [{$abstract}]. Register it in the container before resolving."
            );
        }

        return ($this->bindings[$abstract])($this);
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]);
    }
}