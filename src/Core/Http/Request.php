<?php

declare(strict_types=1);

namespace Arcos\Core\Http;

class Request
{
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array  $headers,
        private readonly array  $query,
        private readonly array  $body,
    ) {}

    public static function fromGlobals(UriResolverInterface $uriResolver): static
    {
        return new static(
            method:  strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            uri:     $uriResolver->resolve(),
            headers: getallheaders() ?: [],
            query:   $_GET,
            body:    self::parseBody(),
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function query(): array
    {
        return $this->query;
    }

    public function body(): array
    {
        return $this->body;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    private static function parseBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            return json_decode($raw, associative: true) ?? [];
        }

        return $_POST;
    }
}