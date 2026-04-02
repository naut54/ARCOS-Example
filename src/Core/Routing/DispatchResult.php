<?php

declare(strict_types=1);

namespace Arcos\Core\Routing;

use Arcos\Core\Http\Response;

final class DispatchResult
{
    public function __construct(
        public readonly Response $response,
        public readonly array $skippedMandatory, // MandatoryMiddlewareInterface[]
    ) {}
}