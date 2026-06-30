<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * A top-level block of a Vue single-file component — `<script>`, `<template>` or
 * `<style>` — with its raw attributes (`setup`, `lang="ts"`, `scoped`), its inner
 * content, the 1-based line it opens on (the order of blocks is a sin all its own:
 * script belongs before template), and the byte offset its inner content starts at
 * (just past the opening tag — where an import is spliced in).
 */
final class Block
{
    /**
     * @param  array<string, string|null>  $attributes
     */
    public function __construct(
        public readonly string $tag,
        public readonly array $attributes,
        public readonly string $content,
        public readonly int $line,
        public readonly int $start = 0,
    ) {}

    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    public function attribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }
}
