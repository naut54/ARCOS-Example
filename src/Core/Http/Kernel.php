<?php

declare(strict_types=1);

namespace Arcos\Core\Http;

use Arcos\Core\Container\Container;
use Arcos\Core\Middleware\MandatoryMiddlewareInterface;
use Arcos\Core\Middleware\SkipRegistry;
use Arcos\Core\Routing\Router;
use Throwable;

class Kernel
{
    public function __construct(
        private readonly Container $container,
        private readonly Router    $router,
    ) {}

    public function handle(Request $request): void
    {
        SkipRegistry::reset();

        try {
            $result   = $this->router->dispatch($request, $this->container);
            $response = $this->runMandatorySweep($request, $result->response, $result->skippedMandatory);
        } catch (Throwable $e) {
            $response = $this->handleException($e);
        }

        $response->send();
    }

    private function runMandatorySweep(Request $request, Response $response, array $skipped): Response
    {
        foreach ($skipped as $middleware) {
            /** @var MandatoryMiddlewareInterface $middleware */
            $response = $middleware->handleMandatory($request, $response);
        }

        return $response;
    }

    private function handleException(Throwable $e): Response
    {
        error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

        return new Response(
            body: [
                'success'          => false,
                'error_code'       => 'SYS-001',
                'message'          => 'An unexpected error occurred.',
                'suggested_action' => 'Check the server logs for details.',
            ],
            status: 500,
        );
    }
}