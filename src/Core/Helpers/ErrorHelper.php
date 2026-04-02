<?php

declare(strict_types=1);

namespace Arcos\Core\Helpers;

use Arcos\Core\Http\Response;

class ErrorHelper
{
    private static array $catalog = [
        'RTE-001' => [
            'message'          => 'The requested resource was not found.',
            'suggested_action' => 'Check the URI and try again.',
            'status'           => 404,
            'error_level'      => 'Low',
        ],
        'RTE-002' => [
            'message'          => 'The request method is not allowed for this route.',
            'suggested_action' => 'Check the allowed methods for this route.',
            'status'           => 405,
            'error_level'      => 'Low',
        ],
        'VAL-001' => [
            'message'          => 'The request is missing required fields.',
            'suggested_action' => 'Check the required fields and try again.',
            'status'           => 422,
            'error_level'      => 'Low',
        ],
        'VAL-002' => [
            'message'          => 'The provided data failed validation.',
            'suggested_action' => 'Review the submitted data and correct any invalid fields.',
            'status'           => 422,
            'error_level'      => 'Low',
        ],
        'RES-001' => [
            'message'          => 'The requested resource does not exist.',
            'suggested_action' => 'Verify the resource identifier and try again.',
            'status'           => 404,
            'error_level'      => 'Low',
        ],
        'SYS-001' => [
            'message'          => 'An unexpected error occurred.',
            'suggested_action' => 'Check the server logs for details.',
            'status'           => 500,
            'error_level'      => 'High',
        ],
        'SYS-002' => [
            'message'          => 'A required service is currently unavailable.',
            'suggested_action' => 'Try again later or contact support.',
            'status'           => 503,
            'error_level'      => 'High',
        ],
    ];

    public static function respond(string $errorCode, ?string $override = null): Response
    {
        if (!isset(self::$catalog[$errorCode])) {
            return self::respond('SYS-001');
        }

        $entry = self::$catalog[$errorCode];

        return new Response(
            body: [
                'success'          => false,
                'error_level'      => $entry['error_level'],
                'error_code'       => $errorCode,
                'message'          => $override ?? $entry['message'],
                'suggested_action' => $entry['suggested_action'],
            ],
            status: $entry['status'],
        );
    }
}