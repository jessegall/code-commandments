<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend;

/**
 * A plain DTO that happens to expose a `get()` and a single same-named
 * accessor (`string()`) but is NOT a typed bag — it lacks the accessor family.
 * The prophet's structural gate must keep silent here: coercing a value read
 * off this object is not re-implementing a bag accessor.
 */
class PlainDto
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function string(string $key): string
    {
        return (string) $this->get($key);
    }
}
