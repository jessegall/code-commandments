<?php

namespace App\RegistryCorpus;

use Illuminate\Support\Carbon;

/**
 * REGISTRY: no — it's a TTL cache: stored entries carry an expiry and are
 * evicted on read, so lookups are time-dependent rather than a stable keyed registry.
 */
class TtlResponseCache
{
    /** @var array<string, array{value: mixed, expiresAt: Carbon}> */
    private array $entries = [];

    public function put(string $key, mixed $value, int $ttlSeconds): void
    {
        $this->entries[$key] = [
            'value' => $value,
            'expiresAt' => Carbon::now()->addSeconds($ttlSeconds),
        ];
    }

    public function get(string $key): mixed
    {
        $entry = $this->entries[$key] ?? null;

        if ($entry === null) {
            return null;
        }

        if ($entry['expiresAt']->isPast()) {
            unset($this->entries[$key]);

            return null;
        }

        return $entry['value'];
    }

    public function forget(string $key): void
    {
        unset($this->entries[$key]);
    }
}
