<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Vue\Expr\Expr;
use JesseGall\CodeCommandments\Vue\Expr\Interpolation;
use JesseGall\CodeCommandments\Vue\Expr\Parser;

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

    public function hasAttribute(string|Directive $name): bool
    {
        return array_key_exists($name instanceof Directive ? $name->value : $name, $this->attributes);
    }

    public function attribute(string|Directive $name): ?string
    {
        return $this->attributes[$name instanceof Directive ? $name->value : $name] ?? null;
    }

    /**
     * Is this a directive / bound attribute — one that carries a JS EXPRESSION
     * (`:x`, `@e`, `v-if`) rather than a literal string (`class`, `href`)?
     */
    public function isBindingName(string $name): bool
    {
        return str_starts_with($name, ':')
            || str_starts_with($name, '@')
            || str_starts_with($name, 'v-');
    }

    /**
     * The parsed JS expression of a bound attribute, or null when it isn't one /
     * has no value. The detector's gateway to reasoning over the binding as an AST.
     */
    public function binding(string $name): ?Expr
    {
        $value = $this->attributes[$name] ?? null;

        return $value !== null && $this->isBindingName($name) ? Parser::parse($value) : null;
    }

    /**
     * Every JS expression this element evaluates — its bound attributes plus the
     * `{{ … }}` interpolations in its OWN text — each as an {@see Expr} tree.
     *
     * @return list<Expr>
     */
    public function expressions(): array
    {
        $expressions = [];

        foreach ($this->attributes as $name => $value) {
            if ($value !== null && $this->isBindingName($name)) {
                $expressions[] = Parser::parse($value);
            }
        }

        foreach ($this->children as $child) {
            if ($child->isText()) {
                foreach (Interpolation::extract($child->text) as $body) {
                    $expressions[] = Parser::parse($body);
                }
            }
        }

        return $expressions;
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
     * Is this a `<template>` that only makes sense inside its parent — a slot
     * (`#name` / `v-slot`) or a `v-else` / `v-else-if` continuation? Such a block
     * can't stand alone as a component root; an extraction must lift its CONTENT, not
     * the wrapper.
     */
    public function isContextBound(): bool
    {
        if (strtolower($this->tag) !== 'template') {
            return false;
        }

        if ($this->hasAttribute(Directive::Else) || $this->hasAttribute(Directive::ElseIf)) {
            return true;
        }

        foreach (array_keys($this->attributes) as $name) {
            if (str_starts_with($name, '#') || $name === 'v-slot' || str_starts_with($name, 'v-slot:')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every element in this subtree, self excluded, in document order — the whole
     * reach of a component when walking it for clusters.
     *
     * @return list<Element>
     */
    public function descendants(): array
    {
        $descendants = [];

        foreach ($this->elements() as $child) {
            $descendants[] = $child;

            foreach ($child->descendants() as $deeper) {
                $descendants[] = $deeper;
            }
        }

        return $descendants;
    }

    /**
     * This element then each ancestor up to the root — the spine used to find the
     * lowest common ancestor of a set of elements (their shared extraction boundary).
     *
     * @return list<Element>
     */
    public function ancestry(): array
    {
        $spine = [];

        for ($node = $this; $node !== null; $node = $node->parent) {
            $spine[] = $node;
        }

        return $spine;
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
