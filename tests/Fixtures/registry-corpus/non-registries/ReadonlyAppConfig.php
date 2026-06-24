<?php

namespace App\RegistryCorpus;

use Illuminate\Support\Arr;

/**
 * REGISTRY: no — a read-only config bag: values are injected once via the
 * constructor and only ever READ back out; there is no put/register path to
 * accumulate keyed entries, so it's a frozen lookup table, not a registry.
 */
final class ReadonlyAppConfig
{
    public function __construct(
        private readonly array $values,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->values, $key, $default);
    }

    public function has(string $key): bool
    {
        return Arr::has($this->values, $key);
    }

    public function all(): array
    {
        return $this->values;
    }
}
