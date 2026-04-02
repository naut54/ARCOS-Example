<?php

declare(strict_types=1);

namespace Arcos\Core\Middleware;

use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;

interface MandatoryMiddlewareInterface
{
    public function handleMandatory(Request $request, Response $response): Response;
}