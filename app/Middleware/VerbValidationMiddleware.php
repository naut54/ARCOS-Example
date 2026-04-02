<?php

declare(strict_types=1);

namespace App\Middleware;

use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;
use Arcos\Core\Middleware\MiddlewareInterface;
use Arcos\Core\Middleware\RouteAwareInterface;

class VerbValidationMiddleware implements MiddlewareInterface, RouteAwareInterface
{
    public function __construct(
        // Not readonly: withAllowedMethods() needs to mutate this on cloned instances
        private array $allowedMethods = [],
    ) {}

    public function withAllowedMethods(array $methods): static
    {
        $clone = clone $this;
        $clone->allowedMethods = $methods;
        return $clone;
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!in_array($request->method(), $this->allowedMethods, strict: true)) {
            return new Response(
                body: [
                    'success'          => false,
                    'error_code'       => 'RTE-002',
                    'message'          => "Method [{$request->method()}] is not allowed for this route.",
                    'suggested_action' => 'Allowed methods: ' . implode(', ', $this->allowedMethods),
                ],
                status: 405,
            );
        }

        return $next($request);
    }
}