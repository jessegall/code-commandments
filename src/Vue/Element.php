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
 * sit in a layer above it.
 */
final class Element
{
    public ?Element $parent = null;

    /**
     * @param  array<string, string|null>  $attributes  directive name => value (null = valueless)
     * @param  list<Element>  $children
     */
    public function __construct(
        public readonly string $tag,
        public readonly array $attributes,
        public readonly array $children,
        public readonly int $line,
        public readonly string $text = '',
    ) {}

    public function isText(): bool
    {
        return $this->tag === '#text';
    }

    public function isRoot(): bool
    {
        return $this->tag === '#root';
    }

    /**
     * A real element — not text, not the fragment root.
     */
    public function isElement(): bool
    {
        return ! $this->isText() && ! $this->isRoot();
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
}
