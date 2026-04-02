<?php

declare(strict_types=1);

namespace Arcos\Core\Middleware;

use LogicException;

final class MiddlewareLink
{
    public function __construct(
        public readonly string  $middleware,
        public readonly bool    $isMandatory  = false,
        public readonly bool    $isSkippable  = false,
        public readonly bool    $canHaveGroup = true,
        public readonly ?string $name         = null,
    ) {
        if (!is_a($this->middleware, MiddlewareInterface::class, allow_string: true)) {
            throw new LogicException(
                "[{$this->middleware}] must implement MiddlewareInterface to be used as a MiddlewareLink."
            );
        }

        if ($this->isMandatory && !is_a($this->middleware, MandatoryMiddlewareInterface::class, allow_string: true)) {
            throw new LogicException(
                "[{$this->middleware}] is declared as mandatory but does not implement MandatoryMiddlewareInterface."
            );
        }

        if ($this->isMandatory && $this->isSkippable) {
            throw new LogicException(
                "[{$this->middleware}] cannot be both mandatory and skippable. These attributes are mutually exclusive."
            );
        }
    }

    public static function ref(string $middlewareClass): static
    {
        return new static($middlewareClass);
    }
}