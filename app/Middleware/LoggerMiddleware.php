<?php

declare(strict_types=1);

namespace App\Middleware;

use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;
use Arcos\Core\Middleware\MandatoryMiddlewareInterface;
use Arcos\Core\Middleware\MiddlewareInterface;

class LoggerMiddleware implements MiddlewareInterface, MandatoryMiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $start    = hrtime(true);
        $response = $next($request);
        $elapsed  = round((hrtime(true) - $start) / 1_000_000, 2);

        $this->log($request, $response, $elapsed);

        return $response;
    }

    public function handleMandatory(Request $request, Response $response): Response
    {
        $this->log($request, $response, elapsed: null);

        return $response;
    }

    private function log(Request $request, Response $response, ?float $elapsed): void
    {
        $parts = [
            '[' . date('Y-m-d H:i:s') . ']',
            $request->method(),
            $request->uri(),
            $response->status(),
        ];

        if ($elapsed !== null) {
            $parts[] = "{$elapsed}ms";
        }

        error_log(implode(' ', $parts));
    }
}