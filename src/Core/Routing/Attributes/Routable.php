<?php

declare(strict_types=1);

namespace Arcos\Core\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Routable
{
    public function __construct(
        public readonly array $methods, // ['GET'], ['POST', 'PUT'], etc.
    ) {}
}