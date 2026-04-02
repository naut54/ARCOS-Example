<?php

declare(strict_types=1);

namespace Arcos\Core\Middleware;

use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}