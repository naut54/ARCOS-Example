<?php

declare(strict_types=1);

namespace Arcos\Core\Http\Resolvers;

use Arcos\Core\Http\UriResolverInterface;

class QueryParamUriResolver implements UriResolverInterface
{
    public function __construct(
        private readonly string $param = 'url',
    ) {}

    public function resolve(): string
    {
        $value = $_GET[$this->param] ?? '';

        if ($value === '') {
            return '/';
        }

        return '/' . ltrim($value, '/');
    }
}