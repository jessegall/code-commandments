<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * One node of a parsed Vue template: an element (`<div>`, `<MyComponent>`,
 * `<template>`), a text node (`tag === '#text'`), or the synthetic fragment root
 * (`tag === '#root'`) that holds a template's top-level nodes.
 *
 * Attributes keep Vue's raw directive names (`v-if`, `:title`, `@click`,
 * `#default`); navigation up the tree is via {@see parent}, wired once the children
 * are built. Like the backend's AstNode this is a thin data node — fluent queries
 * sit in a layer above it, and {@see ElementMatch} extends it to add `file:line`.
 */
class Element
{
    public ?Element $parent = null;

    /**
     * @param  array<string, string|null>  $attributes  directive name => value (null = valueless)
     * @param  list<Element>  $children
     * @param  int  $start  byte offset of this node's `<` in the SFC source
     * @param  int  $end    byte offset just past this node (after `>` / `</tag>`)
     */
    public function __construct(
        public readonly string $tag,
        public readonly array $attributes,
        public readonly array $children,
        public readonly int $line,
        public readonly string $text = '',
        public readonly int $start = 0,
        public readonly int $end = 0,
    ) {}

    public function isText(): bool
    {
        return $this->tag === '#text';
    }

    public function isRoot(): bool
    {
        return $this->tag === '#root';
    }

    public function isComment(): bool
    {
        return $this->tag === '#comment';
    }

    /**
     * A real element — not text, comment, or the fragment root (the synthetic nodes
     * all use a `#`-prefixed tag).
     */
    public function isElement(): bool
    {
        return ! str_starts_with($this->tag, '#');
    }

    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    public function attribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * The element children (text nodes dropped).
     *
     * @return list<Element>
     */
    public function elements(): array
    {
        return array_values(array_filter($this->children, static fn (Element $child): bool => $child->isElement()));
    }

    /**
     * The element siblings that follow this one, in order (text skipped) — how a
     * `v-if` / `v-else-if` / `v-else` chain is read off the tree.
     *
     * @return list<Element>
     */
    public function followingElements(): array
    {
        if ($this->parent === null) {
            return [];
        }

        $siblings = $this->parent->elements();
        $index = array_search($this, $siblings, true);

        return $index === false ? [] : array_values(array_slice($siblings, $index + 1));
    }

    /**
     * A fingerprint of this subtree by STRUCTURE, not source: tag, attributes (order-
     * independent, values included), and children recursively — with formatting and
     * whitespace normalised away and comments ignored. Two blocks with the same
     * fingerprint are identical markup wherever they sit, on whatever lines.
     */
    public function structureHash(): string
    {
        return md5($this->canonical());
    }

    /**
     * How many elements this subtree contains (itself included) — a size to floor
     * out trivial look-alikes from real extractable chunks.
     */
    public function subtreeSize(): int
    {
        $size = $this->isElement() ? 1 : 0;

        foreach ($this->children as $child) {
            $size += $child->subtreeSize();
        }

        return $size;
    }

    private function canonical(): string
    {
        if ($this->isText()) {
            $text = (string) preg_replace('/\s+/', ' ', trim($this->text));

            return $text === '' ? '' : "T:{$text}";
        }

        if (! $this->isElement()) {
            return '';
        }

        $attributes = $this->attributes;
        ksort($attributes);

        $pairs = [];
        foreach ($attributes as $name => $value) {
            $pairs[] = $value === null ? $name : "{$name}={$value}";
        }

        $children = '';
        foreach ($this->children as $child) {
            $children .= $child->canonical();
        }

        return 'E:' . $this->tag . '[' . implode(',', $pairs) . '](' . $children . ')';
    }
}
