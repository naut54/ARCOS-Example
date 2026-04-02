<?php

declare(strict_types=1);

namespace Arcos\Core\Routing;

use Arcos\Core\Http\UriResolverInterface;

final class SubdomainContext
{
    public function __construct(
        public readonly string               $subdomain,
        public readonly UriResolverInterface $resolver,
        public readonly array                $middlewareGroups,
        public readonly bool                 $autoResolve          = false,
        public readonly string               $controllersNamespace  = 'App\\Controllers',
    ) {}
}