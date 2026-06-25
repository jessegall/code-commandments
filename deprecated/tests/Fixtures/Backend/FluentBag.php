<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend;

/**
 * A minimal Fluent-like bag — the STRUCTURAL shape PreferNativeTypedAccessor
 * fires on: untyped `get()`/`input()`/`query()` PLUS a full family of keyed
 * typed accessors. Real (autoloadable) so the prophet's reflection-driven
 * structural gate can confirm it, exactly as it would against Laravel's
 * `Request`/`Fluent` in a consumer.
 */
class FluentBag
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->get($key, $default);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->get($key, $default);
    }

    public function string(string $key, ?string $default = null): string
    {
        return (string) $this->get($key, $default);
    }

    public function integer(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function float(string $key, float $default = 0.0): float
    {
        return (float) $this->get($key, $default);
    }

    public function boolean(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function array(string $key): array
    {
        $value = $this->get($key, []);

        return is_array($value) ? $value : [];
    }
}
