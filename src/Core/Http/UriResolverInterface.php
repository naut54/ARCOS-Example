<?php

declare(strict_types=1);

namespace Arcos\Core\Http;

interface UriResolverInterface
{
    public function resolve(): string;
}