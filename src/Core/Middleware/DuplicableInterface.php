<?php

declare(strict_types=1);

namespace Arcos\Core\Middleware;

interface DuplicableInterface
{
    public static function allowDuplicates(): bool;
}