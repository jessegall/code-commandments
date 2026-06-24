<?php
namespace Acme\Notify\Exempt;

final class RequestParser
{
    /** @var array<string, mixed> */
    private array $data = [];

    // Reads request input and returns null for absent/invalid — the HTTP boundary
    // idiom, exempt from "model the absence".
    public function name(): string|null
    {
        $value = $this->input('name');
        return is_string($value) ? $value : null;
    }

    private function input(string $key): mixed { return $this->data[$key] ?? null; }
}
