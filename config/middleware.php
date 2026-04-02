<?php

declare(strict_types=1);

use Arcos\Core\Middleware\MiddlewareGroup;
use Arcos\Core\Middleware\MiddlewareLink;
use Arcos\Core\Routing\Router;
use App\Middleware\LoggerMiddleware;
use App\Middleware\VerbValidationMiddleware;

return function (Router $router): void {
    // Global middleware
    // Runs on every route, always outermost. canHaveGroup: false — belongs
    // to no group, it is a framework-level concern above all chains.

    $router->always([
        new MiddlewareLink(LoggerMiddleware::class, isMandatory: true, canHaveGroup: false),
    ]);

    // Groups

    $router->group('api', [
        new MiddlewareLink(VerbValidationMiddleware::class),
    ]);

    // Per-route middleware

    $router->middleware('GET',    '/products', [MiddlewareGroup::ref('api')]);
    $router->middleware('GET',    '/products/show', [MiddlewareGroup::ref('api')]);
    $router->middleware('POST',   '/products', [MiddlewareGroup::ref('api')]);
    $router->middleware('PATCH',  '/products/update', [MiddlewareGroup::ref('api')]);
    $router->middleware('DELETE', '/products/delete', [MiddlewareGroup::ref('api')]);
};