<?php

namespace App\RegistryCorpus;

use App\RegistryCorpus\Channels\Channel;
use RuntimeException;

/**
 * REGISTRY: yes — a plain keyed array you register() Channel objects into by key
 * and get() them back out by the same key; the canonical put/lookup shape.
 */
class ArrayChannelRegistry
{
    /** @var array<string, Channel> */
    private array $channels = [];

    public function register(string $key, Channel $channel): void
    {
        $this->channels[$key] = $channel;
    }

    public function get(string $key): Channel
    {
        return $this->channels[$key]
            ?? throw new RuntimeException("No channel registered for [{$key}].");
    }

    public function has(string $key): bool
    {
        return isset($this->channels[$key]);
    }

    /** @return array<string, Channel> */
    public function all(): array
    {
        return $this->channels;
    }
}
