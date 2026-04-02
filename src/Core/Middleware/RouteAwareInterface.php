<?php

namespace Arcos\Core\Middleware;

interface RouteAwareInterface
{
    public function withAllowedMethods(array $methods): static;
}