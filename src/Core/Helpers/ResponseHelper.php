<?php

declare(strict_types=1);

namespace Arcos\Core\Helpers;

use Arcos\Core\Http\Response;

class ResponseHelper
{
    public static function ok(array $data): Response
    {
        return new Response(
            body:   [
                'success' => true,
                'data'    => $data,
            ],
            status: 200,
        );
    }

    public static function created(array $data): Response
    {
        return new Response(
            body:   [
                'success' => true,
                'data'    => $data,
            ],
            status: 201,
        );
    }

    public static function noContent(): Response
    {
        return new Response(
            body:   null,
            status: 204,
        );
    }

    public static function message(string $message, int $status = 200): Response
    {
        return new Response(
            body:   [
                'success' => true,
                'message' => $message,
            ],
            status: $status,
        );
    }
}