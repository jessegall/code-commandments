<?php

namespace App\RegistryCorpus;

use Illuminate\Support\Collection;

/**
 * REGISTRY: yes — you store Tag values under string keys with withTag() and
 * fetch them back by key with get(); it is keyed put/lookup, just functional
 * (each put returns a fresh copy instead of mutating).
 */
final class ImmutableTagRegistry
{
    /** @param Collection<string, Tag> $tags */
    private function __construct(private readonly Collection $tags)
    {
    }

    public static function empty(): self
    {
        return new self(collect());
    }

    public function withTag(string $key, Tag $tag): self
    {
        return new self($this->tags->toBase()->put($key, $tag));
    }

    public function get(string $key): Tag
    {
        return $this->tags->get($key)
            ?? throw new \OutOfBoundsException("No tag registered for [{$key}].");
    }

    public function has(string $key): bool
    {
        return $this->tags->has($key);
    }
}
