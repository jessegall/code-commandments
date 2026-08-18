<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\PhpTypes\Option;

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
     * @var array<string, string|null>
     */
    public readonly array $attributes;

    /**
     * @param  string  $attributes  the opening tag's attribute text, exactly as written
     */
    public function __construct(
        public readonly string $tag,
        string $attributes,
        public readonly string $content,
        public readonly int $line,
        public readonly int $start = 0,
    ) {
        // Every caller held the raw text and parsed it on the way in, so the parse belongs here —
        // one rule about what an attribute list means, rather than one per construction site.
        $this->attributes = Attributes::parse($attributes);
    }

    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * The VALUE of attribute $name — none when the block doesn't carry it, and none when
     * it carries it without one (`setup`, `scoped`). Presence is {@see hasAttribute}.
     *
     * @return Option<string>
     */
    public function attribute(string $name): Option
    {
        return Option::fromNullable($this->attributes[$name] ?? null);
    }
}
