<?php

declare(strict_types=1);

namespace Arcos\Core\Http\Resolvers;

use Arcos\Core\Http\UriResolverInterface;

class PathUriResolver implements UriResolverInterface
{
    public function resolve(): string
    {
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');

        return '/' . ltrim($uri ?: '/', '/');
    }
}