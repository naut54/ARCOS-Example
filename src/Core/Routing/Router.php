<?php

declare(strict_types=1);

namespace Arcos\Core\Routing;

use Arcos\Core\Container\Container;
use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;
use Arcos\Core\Http\UriResolverInterface;
use Arcos\Core\Middleware\DuplicableInterface;
use Arcos\Core\Middleware\MandatoryMiddlewareInterface;
use Arcos\Core\Middleware\MiddlewareGroup;
use Arcos\Core\Middleware\MiddlewareInterface;
use Arcos\Core\Middleware\MiddlewareLink;
use Arcos\Core\Middleware\MiddlewareRemove;
use Arcos\Core\Middleware\RouteAwareInterface;
use Arcos\Core\Middleware\SkipRegistry;
use LogicException;

class Router
{
    private array   $routes             = [];
    private array   $middleware         = [];
    private array   $globalMiddleware   = []; // MiddlewareLink[]
    private array   $groups             = []; // string => MiddlewareGroup
    private array   $subdomainContexts  = []; // string => SubdomainContext
    private ?string $activeSubdomain    = null;
    private ?string $currentSubdomain   = null; // set during subdomain() block execution

    // Subdomain registration

    public function registerSubdomain(
        string               $subdomain,
        UriResolverInterface $resolver,
        array                $middlewareGroups = [],
        bool                 $autoResolve      = false,
        string               $controllersNamespace = 'App\\Controllers',
    ): void {
        if (isset($this->subdomainContexts[$subdomain])) {
            throw new LogicException(
                "Subdomain [{$subdomain}] is already registered. Subdomain names must be unique."
            );
        }

        $this->subdomainContexts[$subdomain] = new SubdomainContext(
            subdomain:            $subdomain,
            resolver:             $resolver,
            middlewareGroups:     $middlewareGroups,
            autoResolve:          $autoResolve,
            controllersNamespace: $controllersNamespace,
        );
    }

    /**
     * Called after all route files are loaded.
     * Triggers auto-resolution for any subdomain that opted in.
     */
    public function boot(string $basePath): void
    {
        foreach ($this->subdomainContexts as $context) {
            if (!$context->autoResolve) {
                continue;
            }

            $resolver = new AutoResolver(
                router:    $this,
                namespace: $context->controllersNamespace,
                basePath:  $basePath,
                subdomain: $context->subdomain,
            );

            $resolver->resolve();
        }
    }

    public function setActiveSubdomain(string $subdomain): void
    {
        if (!isset($this->subdomainContexts[$subdomain])) {
            throw new LogicException(
                "Cannot activate subdomain [{$subdomain}] — it has not been registered. " .
                "Call registerSubdomain() before setActiveSubdomain()."
            );
        }

        $this->activeSubdomain = $subdomain;
    }

    public function activeResolver(): UriResolverInterface
    {
        if ($this->activeSubdomain === null) {
            throw new LogicException(
                "No active subdomain set. Call setActiveSubdomain() before activeResolver()."
            );
        }

        return $this->subdomainContexts[$this->activeSubdomain]->resolver;
    }

    // Route registration

    public function subdomain(string $subdomain, callable $callback): void
    {
        if (!isset($this->subdomainContexts[$subdomain])) {
            throw new LogicException(
                "Subdomain [{$subdomain}] referenced in route declaration is not registered. " .
                "Call registerSubdomain() in index.php before defining routes for it."
            );
        }

        $previous               = $this->currentSubdomain;
        $this->currentSubdomain = $subdomain;

        $callback($this);

        $this->currentSubdomain = $previous;
    }

    public function add(string $method, string $uri, string $controller, string $action, string $source = 'explicit'): void
    {
        if ($this->currentSubdomain === null) {
            throw new LogicException(
                "Route [{$method} {$uri}] was declared outside a subdomain() block. " .
                "Every route must belong to a subdomain."
            );
        }

        $this->routes[] = [
            'method'     => strtoupper($method),
            'uri'        => $uri,
            'controller' => $controller,
            'action'     => $action,
            'subdomain'  => $this->currentSubdomain,
            'source'     => $source
        ];
    }

    public function always(array $middleware): void
    {
        $links = array_map(fn($m) => $this->toLink($m), $middleware);

        $this->checkDuplicates($links, $this->globalMiddleware, 'global');

        $this->globalMiddleware = array_merge($this->globalMiddleware, $links);
    }

    public function group(string $name, array $links): void
    {
        if (isset($this->groups[$name])) {
            throw new LogicException(
                "Middleware group [{$name}] is already defined. Group names must be unique."
            );
        }

        $this->groups[$name] = MiddlewareGroup::define($name, $links);
    }

    public function middleware(string $method, string $uri, array $middleware): void
    {
        $entries  = array_map(fn($m) => $this->toEntry($m), $middleware);
        $existing = $this->middleware[$method][$uri] ?? [];

        $resolvedIncoming = $this->expandLinks($entries, "route [{$method} {$uri}]");
        $resolvedExisting = $this->expandLinks($existing, "route [{$method} {$uri}]");

        $this->checkDuplicates($resolvedIncoming, $this->globalMiddleware, "route [{$method} {$uri}] against global");
        $this->checkDuplicates($resolvedIncoming, $resolvedExisting, "route [{$method} {$uri}]");

        $this->middleware[$method][$uri] = array_merge($existing, $entries);
    }

    // Dispatch

    public function dispatch(Request $request, Container $container): DispatchResult
    {
        if ($this->activeSubdomain === null) {
            throw new LogicException(
                "No active subdomain set. Call setActiveSubdomain() before dispatching."
            );
        }

        $match = $this->resolve($request);

        if ($match === null) {
            return new DispatchResult($this->notFound(), skippedMandatory: []);
        }

        if (isset($match['_error']) && $match['_error'] === 405) {
            return new DispatchResult($this->methodNotAllowed(), skippedMandatory: []);
        }

        return $this->buildChainAndExecute($request, $match, $container);
    }

    // Resolution

    private function resolve(Request $request): ?array
    {
        $uriMatch = false;

        foreach ($this->routes as $route) {
            // Only consider routes belonging to the active subdomain
            if ($route['subdomain'] !== $this->activeSubdomain) {
                continue;
            }

            if ($route['uri'] === $request->uri()) {
                $uriMatch = true;
                if ($route['method'] === $request->method()) {
                    return $route;
                }
            }
        }

        return $uriMatch ? ['_error' => 405] : null;
    }

    // Dumping

    public function dumpRoutes(): array
    {
        $output = [];

        foreach ($this->routes as $route) {
            $subdomain = $route['subdomain'];
            $method    = $route['method'];
            $uri       = $route['uri'];
            $context   = "route [{$method} {$uri}]";

            $middleware = [];

            // Layer 1 — global
            foreach ($this->globalMiddleware as $link) {
                $middleware[] = $this->serializeLink($link, 'global');
            }

            // Layer 2 — subdomain groups, expanded in order
            $subdomainContext = $this->subdomainContexts[$subdomain];

            foreach ($subdomainContext->middlewareGroups as $groupName) {
                if (!isset($this->groups[$groupName])) {
                    // Group not defined yet — skip silently; boot() would have caught this
                    // for the active subdomain, but other subdomains are not validated at boot.
                    continue;
                }

                foreach ($this->groups[$groupName]->links as $link) {
                    $middleware[] = $this->serializeLink($link, 'subdomain_group');
                }
            }

            // Layer 3 — per-route entries, expanded (MiddlewareGroup refs resolved inline)
            $perRouteEntries = $this->middleware[$method][$uri] ?? [];
            $perRouteLinks   = $this->expandLinks($perRouteEntries, $context);

            foreach ($perRouteLinks as $link) {
                $middleware[] = $this->serializeLink($link, 'per_route');
            }

            $output[] = [
                'method'     => $method,
                'uri'        => $uri,
                'controller' => $route['controller'],
                'action'     => $route['action'],
                'subdomain'  => $subdomain,
                'source'     => $route['source'] ?? 'explicit',
                'middleware' => $middleware,
            ];
        }

        return $output;
    }

    public function dumpGroups(): array
    {
        $output = [];

        foreach ($this->groups as $name => $group) {
            $links = [];

            foreach ($group->links as $link) {
                $links[] = [
                    'class'       => $link->middleware,
                    'isMandatory' => $link->isMandatory,
                    'isSkippable' => $link->isSkippable,
                    'name'        => $link->name,
                ];
            }

            $output[$name] = [
                'name'  => $name,
                'links' => $links,
            ];
        }

        return $output;
    }

    private function serializeLink(MiddlewareLink $link, string $layer): array
    {
        return [
            'class'       => $link->middleware,
            'isMandatory' => $link->isMandatory,
            'isSkippable' => $link->isSkippable,
            'name'        => $link->name,
            'layer'       => $layer,
        ];
    }

    // Chain construction

    private function buildChainAndExecute(Request $request, array $match, Container $container): DispatchResult
    {
        $context = "route [{$match['method']} {$match['uri']}]";

        // Global → subdomain groups → per-route
        $subdomainEntries = $this->resolveSubdomainGroupEntries();

        $entries = array_merge(
            $this->globalMiddleware,
            $subdomainEntries,
            $this->middleware[$match['method']][$match['uri']] ?? [],
        );

        $links           = $this->expandLinks($entries, $context);
        $allowedMethods  = $this->resolveAllowedMethods($match['uri']);
        $executedClasses = [];

        $core = function (Request $request) use ($match, $container): Response {
            $controller = $container->make($match['controller']);
            $action     = $match['action'];
            return $controller->$action($request);
        };

        $chain = array_reduce(
            array_reverse($links),
            function (callable $next, MiddlewareLink $link) use ($container, $allowedMethods, &$executedClasses): callable {
                return function (Request $request) use ($next, $link, $container, $allowedMethods, &$executedClasses): Response {
                    if ($link->isSkippable && SkipRegistry::current()->shouldSkip($link->middleware)) {
                        return $next($request);
                    }

                    /** @var MiddlewareInterface $middleware */
                    $middleware = $container->make($link->middleware);

                    if ($middleware instanceof RouteAwareInterface) {
                        $middleware = $middleware->withAllowedMethods($allowedMethods);
                    }

                    $executedClasses[] = $link->middleware;

                    return $middleware->handle($request, $next);
                };
            },
            $core,
        );

        $response = $chain($request);

        $skippedMandatory = $this->resolveSkippedMandatory($links, $executedClasses, $container);

        return new DispatchResult($response, $skippedMandatory);
    }

    /**
     * Expand the active subdomain's middleware group names into MiddlewareGroup refs.
     */
    private function resolveSubdomainGroupEntries(): array
    {
        $context = $this->subdomainContexts[$this->activeSubdomain];
        $entries = [];

        foreach ($context->middlewareGroups as $groupName) {
            if (!isset($this->groups[$groupName])) {
                throw new LogicException(
                    "Subdomain [{$this->activeSubdomain}] references middleware group [{$groupName}] " .
                    "which is not defined. Register it with \$router->group() before use."
                );
            }

            $entries[] = MiddlewareGroup::ref($groupName);
        }

        return $entries;
    }

    // Link expansion

    private function expandLinks(array $entries, string $context): array
    {
        $links    = [];
        $removals = [];

        foreach ($entries as $entry) {
            if ($entry instanceof MiddlewareRemove) {
                $removals[] = $entry->middleware;
                continue;
            }

            if ($entry instanceof MiddlewareLink) {
                $links[] = $entry;
                continue;
            }

            if ($entry instanceof MiddlewareGroup) {
                if (!isset($this->groups[$entry->name])) {
                    throw new LogicException(
                        "Middleware group [{$entry->name}] referenced in {$context} is not defined. " .
                        "Register it with \$router->group() before use."
                    );
                }

                foreach ($this->groups[$entry->name]->links as $link) {
                    $links[] = $link;
                }

                continue;
            }

            throw new LogicException("Unexpected entry type in middleware chain for {$context}.");
        }

        foreach ($removals as $class) {
            $found = false;

            foreach ($links as $link) {
                if ($link->middleware === $class) {
                    $found = true;
                    break;
                }
            }

            foreach ($this->globalMiddleware as $link) {
                if ($link->middleware === $class) {
                    $found = true;
                    if ($link->isMandatory) {
                        throw new LogicException(
                            "Cannot remove [{$class}] from {$context} because it is declared as mandatory."
                        );
                    }
                    break;
                }
            }

            if (!$found) {
                throw new LogicException(
                    "Cannot remove [{$class}] from {$context} because it is not present in the chain."
                );
            }
        }

        return array_values(array_filter(
            $links,
            fn(MiddlewareLink $link) => !in_array($link->middleware, $removals, strict: true)
        ));
    }

    private function resolveSkippedMandatory(array $links, array $executedClasses, Container $container): array
    {
        $skipped = [];

        foreach ($links as $link) {
            if (!$link->isMandatory) {
                continue;
            }

            if (in_array($link->middleware, $executedClasses, strict: true)) {
                continue;
            }

            $skipped[] = $container->make($link->middleware);
        }

        return $skipped;
    }

    // Helpers

    private function toLink(MiddlewareLink|string $middleware): MiddlewareLink
    {
        return $middleware instanceof MiddlewareLink
            ? $middleware
            : new MiddlewareLink($middleware);
    }

    private function toEntry(MiddlewareGroup|MiddlewareLink|MiddlewareRemove|string $entry): MiddlewareGroup|MiddlewareLink|MiddlewareRemove
    {
        if ($entry instanceof MiddlewareGroup
            || $entry instanceof MiddlewareLink
            || $entry instanceof MiddlewareRemove) {
            return $entry;
        }

        return new MiddlewareLink($entry);
    }

    private function checkDuplicates(array $incoming, array $existing, string $context): void
    {
        $existingClasses = array_map(fn(MiddlewareLink $l) => $l->middleware, $existing);

        foreach ($incoming as $link) {
            if (!in_array($link->middleware, $existingClasses, strict: true)) {
                continue;
            }

            $allowsDuplicates = is_a($link->middleware, DuplicableInterface::class, allow_string: true)
                && $link->middleware::allowDuplicates();

            if (!$allowsDuplicates) {
                throw new LogicException(
                    "Duplicate middleware [{$link->middleware}] detected in {$context}. " .
                    "Implement DuplicableInterface and return true from allowDuplicates() to allow this explicitly."
                );
            }
        }
    }

    private function resolveAllowedMethods(string $uri): array
    {
        return array_values(array_unique(
            array_map(
                fn($route) => $route['method'],
                array_filter(
                    $this->routes,
                    fn($route) => $route['uri'] === $uri
                        && $route['subdomain'] === $this->activeSubdomain,
                )
            )
        ));
    }

    private function notFound(): Response
    {
        return new Response(
            body: [
                'success'          => false,
                'error_code'       => 'RTE-001',
                'message'          => 'The requested resource was not found.',
                'suggested_action' => 'Check the URI and try again.',
            ],
            status: 404,
        );
    }

    private function methodNotAllowed(): Response
    {
        return new Response(
            body: [
                'success'          => false,
                'error_code'       => 'RTE-002',
                'message'          => 'The request method is not allowed for this route.',
                'suggested_action' => 'Check the allowed methods for this route.',
            ],
            status: 405,
        );
    }
}