<?php

declare(strict_types=1);

use Arcos\Core\Container\Container;
use Arcos\Core\Http\Kernel;
use Arcos\Core\Http\Request;
use Arcos\Core\Http\Resolvers\PathUriResolver;
use Arcos\Core\Routing\Router;
use App\Controllers\ProductsController;
use App\Middleware\LoggerMiddleware;
use App\Middleware\VerbValidationMiddleware;
use App\Services\InventoryService;

// Autoloader

require_once __DIR__ . '/vendor/autoload.php';

// Environment

$env = parse_ini_file(__DIR__ . '/.env');

if ($env === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server configuration error.']);
    exit;
}

foreach (['APP_ENV', 'APP_KEY'] as $required) {
    if (empty($env[$required])) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server configuration error.']);
        exit;
    }
}

foreach ($env as $key => $value) {
    $_ENV[$key] = $value;
}

// Container

$container = new Container();

$container->singleton(InventoryService::class, fn($c) => new InventoryService());

$container->singleton(ProductsController::class, fn($c) =>
new ProductsController($c->make(InventoryService::class))
);

$container->singleton(LoggerMiddleware::class, fn($c) => new LoggerMiddleware());

$container->bind(VerbValidationMiddleware::class, fn($c) => new VerbValidationMiddleware());

// Router

$router = new Router();

// Explicit routing subdomain
$router->registerSubdomain(
    subdomain:        'api',
    resolver:         new PathUriResolver(),
    middlewareGroups: ['api'],
);

// Auto-resolved subdomain — scans App\Controllers at boot
// $router->registerSubdomain(
//     subdomain:            'api',
//     resolver:             new QueryParamUriResolver(param: 'url'),
//     middlewareGroups:     ['api'],
//     autoResolve:          true,
//     controllersNamespace: 'App\\Controllers',
// );

$router->setActiveSubdomain('api');

(require __DIR__ . '/routes/api.php')($router);
(require __DIR__ . '/config/middleware.php')($router);

$router->boot(__DIR__);

// Kernel

if (empty($_SERVER['ARCOS_INSPECT'])) {
    $kernel  = new Kernel($container, $router);
    $request = Request::fromGlobals($router->activeResolver());
    $kernel->handle($request);
}