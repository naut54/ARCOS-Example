<?php

declare(strict_types=1);

namespace Arcos\Services;

use RuntimeException;

abstract class BaseService
{
    protected string $baseUrl   = '';
    protected int    $timeout   = 5;
    protected array  $defaultHeaders = [
        'Content-Type' => 'application/json',
        'Accept'       => 'application/json',
    ];

    protected function get(string $endpoint, array $headers = []): array
    {
        return $this->request('GET', $endpoint, headers: $headers);
    }

    protected function post(string $endpoint, array $payload = [], array $headers = []): array
    {
        return $this->request('POST', $endpoint, $payload, $headers);
    }

    protected function put(string $endpoint, array $payload = [], array $headers = []): array
    {
        return $this->request('PUT', $endpoint, $payload, $headers);
    }

    protected function patch(string $endpoint, array $payload = [], array $headers = []): array
    {
        return $this->request('PATCH', $endpoint, $payload, $headers);
    }

    protected function delete(string $endpoint, array $headers = []): array
    {
        return $this->request('DELETE', $endpoint, headers: $headers);
    }

    private function request(
        string $method,
        string $endpoint,
        array  $payload = [],
        array  $headers = [],
    ): array {
        $url     = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        $headers = array_merge($this->defaultHeaders, $headers);

        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => $this->buildHeaders($headers),
                'content'       => $payload ? json_encode($payload) : null,
                'timeout'       => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($url, context: $context);

        if ($raw === false) {
            return $this->down('Could not reach ' . $url);
        }

        $decoded = json_decode($raw, associative: true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->down('Invalid JSON response from ' . $url);
        }

        return $decoded;
    }

    private function buildHeaders(array $headers): string
    {
        return implode("\r\n", array_map(
            fn($name, $value) => "$name: $value",
            array_keys($headers),
            $headers,
        ));
    }

    protected function ok(array $dependencies = []): array
    {
        return [
            'status'       => 'ok',
            'service'      => static::class,
            'dependencies' => $dependencies,
        ];
    }

    protected function degraded(string $reason, array $dependencies = []): array
    {
        return [
            'status'       => 'degraded',
            'service'      => static::class,
            'reason'       => $reason,
            'dependencies' => $dependencies,
        ];
    }

    protected function down(string $reason): array
    {
        return [
            'status'  => 'down',
            'service' => static::class,
            'reason'  => $reason,
        ];
    }
}