<?php

declare(strict_types=1);

namespace Arcos\Core\Http;

class Response
{
    private array $headers = [];

    public function __construct(
        private readonly mixed $body,
        private readonly int   $status = 200,
    ) {}

    public function withHeader(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): mixed
    {
        return $this->body;
    }

    public function send(): void
    {
        http_response_code($this->status);

        header('Content-Type: application/json');

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        echo json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}