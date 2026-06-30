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
     * @param  array<string, array{int, int}>  $attributeSpans  name => absolute [start, end) of the
     *         attribute in the SFC source, so the write engine removes a directive by its span.
     */
    public function __construct(
        public readonly string $tag,
        public readonly array $attributes,
        public readonly array $children,
        public readonly int $line,
        public readonly string $text = '',
        public readonly int $start = 0,
        public readonly int $end = 0,
        public readonly array $attributeSpans = [],
    ) {}

    /**
     * The absolute `[start, end)` source span of attribute $name — where the write engine
     * splices to remove a directive — or null when it isn't present / wasn't lexed with a span.
     *
     * @return array{int, int}|null
     */
    public function attributeSpan(string|Directive $name): ?array
    {
        return $this->attributeSpans[$name instanceof Directive ? $name->value : $name] ?? null;
    }

    /**
     * The source slice `[$from, $to)` with each named attribute removed by its KNOWN span
     * (each swallowing the space before it, so no `<div  >` gap is left). The AST write that
     * replaces a regex directive-strip: only attributes whose span sits inside the slice are
     * cut, so a directive carried OUT to a call site (a `<template>`'s, outside its content)
     * is left untouched. A directive that sat ALONE on its line takes the whole line with it
     * (its trailing newline too), so no blank line is left behind.
     *
     * @param  list<string|Directive>  $names
     */
    public function sourceOmitting(string $source, int $from, int $to, array $names): string
    {
        $cuts = [];

        foreach ($names as $name) {
            $span = $this->attributeSpan($name);

            if ($span === null || $span[0] < $from || $span[1] > $to) {
                continue;
            }

            $start = $span[0];
            while ($start > $from && ($source[$start - 1] === ' ' || $source[$start - 1] === "\t")) {
                $start--;
            }

            // When the directive was alone on its line — line start before it, only whitespace
            // then a newline after it — swallow that trailing newline so its line collapses
            // entirely rather than leaving a blank one. An inline directive (other content
            // follows before the newline) keeps its line.
            $end = $span[1];

            if ($start === $from || $source[$start - 1] === "\n") {
                $scan = $end;
                while ($scan < $to && ($source[$scan] === ' ' || $source[$scan] === "\t")) {
                    $scan++;
                }

                if ($scan < $to && $source[$scan] === "\n") {
                    $end = $scan + 1;
                }
            }

            $cuts[] = [$start, $end];
        }

        // Splice end-first so earlier offsets stay valid.
        usort($cuts, static fn (array $a, array $b): int => $b[0] <=> $a[0]);

        $text = substr($source, $from, $to - $from);

        foreach ($cuts as [$start, $end]) {
            $text = substr($text, 0, $start - $from) . substr($text, $end - $from);
        }

        return $text;
    }

    public function isText(): bool
    {
        return $this->tag === '#text';
    }

    /**
     * Is this a STATIC text node — real text with no `{{ … }}` interpolation? Decided by the
     * interpolation parser, not a `{{` string scan: a dynamic title can't name a component.
     */
    public function isStaticText(): bool
    {
        return $this->isText()
            && trim($this->text) !== ''
            && Interpolation::extract($this->text) === [];
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
     * Every binding expression for a directive FAMILY — the directive itself and its arg /
     * modifier variants (`v-model`, `v-model:title`, `v-model.lazy`). A node knows its own
     * directives, so the prefix match for the family lives here, not in a detector scanning
     * attribute names by hand.
     *
     * @return list<string>
     */
    public function directiveBindings(Directive $directive): array
    {
        $prefix = $directive->value;
        $bindings = [];

        foreach ($this->attributes as $name => $value) {
            if ($value === null) {
                continue;
            }

            if ($name === $prefix || str_starts_with($name, $prefix . ':') || str_starts_with($name, $prefix . '.')) {
                $bindings[] = $value;
            }
        }

        return $bindings;
    }

    /**
     * The component PROPS this element binds, each to its parsed expression — `:title="t"`
     * / `v-bind:count="n"` → `['title' => <t>, 'count' => <n>]`. Events (`@`), directives
     * (`v-if`), slots (`#`) and static attributes are not prop bindings, so they're excluded;
     * a dynamic arg (`:[key]`) has no static name and is skipped. The edge data of the
     * component graph — what a parent passes a child.
     *
     * @return array<string, Expr>
     */
    public function propBindings(): array
    {
        $bindings = [];

        foreach ($this->attributes as $name => $value) {
            if ($value === null) {
                continue;
            }

            $prop = match (true) {
                str_starts_with($name, ':') => substr($name, 1),
                str_starts_with($name, 'v-bind:') => substr($name, 7),
                default => null,
            };

            if ($prop === null || $prop === '' || $prop[0] === '[') {
                continue;
            }

            // Vue maps a kebab attribute to its camelCase prop (`:order-table` → `orderTable`).
            $bindings[self::camelize($prop)] = Parser::parse($value);
        }

        return $bindings;
    }

    /** A kebab attribute name as its camelCase prop — `order-table` → `orderTable`. No regex. */
    private static function camelize(string $name): string
    {
        $out = '';
        $upper = false;

        for ($i = 0, $length = strlen($name); $i < $length; $i++) {
            if ($name[$i] === '-') {
                $upper = true;

                continue;
            }

            $out .= $upper ? strtoupper($name[$i]) : $name[$i];
            $upper = false;
        }

        return $out;
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
    public function isTemplate(): bool
    {
        return strtolower($this->tag) === 'template';
    }

    /**
     * @see isTemplate — a `<template>` is a fragment wrapper, never a valid component root.
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
     * Does this subtree render an element tagged $tag? The AST answer to "does this markup
     * reference `<$tag>`" — a tree query, never a scan of the rendered source string.
     */
    public function renders(string $tag): bool
    {
        foreach ([$this, ...$this->descendants()] as $element) {
            if ($element->isElement() && $element->tag === $tag) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this a Vue COMPONENT — a PascalCase custom element (`<Dialog>`, `<UserCard>`)
     * rather than a plain HTML tag (`<div>`) or a synthetic node?
     */
    public function isComponent(): bool
    {
        return $this->isElement() && $this->tag !== '' && ctype_upper($this->tag[0]);
    }

    /**
     * The descendant components that belong to THIS component's compound family — those
     * whose tag is this tag plus a suffix (`Dialog` → `DialogContent`, `DialogTitle`,
     * `DialogFooter`). A root with two-or-more such parts is a library compound
     * (Dialog/Card/Sheet/Tabs…) assembled inline — the fingerprint, derived from the
     * tags themselves, no hardcoded list.
     *
     * @return list<Element>
     */
    public function compoundParts(): array
    {
        return array_values(array_filter(
            $this->descendants(),
            fn (Element $element): bool => $element->isComponent() && $element->tag !== $this->tag && str_starts_with($element->tag, $this->tag),
        ));
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
     * How deeply this element is nested — its own level counting from the top (a
     * top-level element is 1). The fragment root and text/comment ancestors don't count.
     */
    public function depth(): int
    {
        $depth = 0;

        for ($node = $this; $node !== null; $node = $node->parent) {
            if ($node->isElement()) {
                $depth++;
            }
        }

        return $depth;
    }

    /**
     * The number of element levels in this subtree — a leaf is 1, a parent is one more
     * than its tallest child. The "how much is still nested below here" measure.
     */
    public function height(): int
    {
        $max = 0;

        foreach ($this->elements() as $child) {
            $max = max($max, $child->height());
        }

        return $max + 1;
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
     * A binding-AGNOSTIC fingerprint: the same as {@see structureHash} but with every
     * value erased — attribute values, class lists, and text content all dropped,
     * keeping only the tag, the attribute NAMES, and the nesting. Two blocks share a
     * shape signature when they render the same skeleton regardless of WHICH data they
     * bind — the structural half of "does an existing component fit this extraction?".
     */
    public function shapeSignature(): string
    {
        return md5($this->shape());
    }

    private function shape(): string
    {
        if ($this->isText()) {
            return trim($this->text) === '' ? '' : 'T';
        }

        if (! $this->isElement()) {
            return '';
        }

        $names = array_keys($this->attributes);
        sort($names);

        $children = '';
        foreach ($this->children as $child) {
            $children .= $child->shape();
        }

        return 'E:' . $this->tag . '[' . implode(',', $names) . '](' . $children . ')';
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

    /** A real component carries CONTENT (this many elements) … */
    private const int MIN_COMPONENT_ELEMENTS = 6;

    /** … AND its own internal STRUCTURE (this many levels — not a flat wrapper). */
    private const int MIN_COMPONENT_DEPTH = 3;

    /**
     * Is this element substantial enough to earn its own component file? A component is a
     * cohesive, structured unit — so it must have real CONTENT (≥ {@see MIN_COMPONENT_ELEMENTS}
     * elements) AND its own internal STRUCTURE (≥ {@see MIN_COMPONENT_DEPTH} levels deep). A
     * thin `<DialogClose><X/><span/></DialogClose>` (flat, 3 elements) is better left inline;
     * a card / section / dialog with nested structure is worth lifting. The SINGLE-boundary
     * extractors gate on this; duplication justifies its own (lower) floor separately.
     */
    public function substantial(): bool
    {
        return $this->subtreeSize() >= self::MIN_COMPONENT_ELEMENTS
            && $this->height() >= self::MIN_COMPONENT_DEPTH;
    }

    /**
     * Collapse every run of whitespace to one space — text normalisation for the structural
     * signature, done char by char (no regex over the content).
     */
    private static function collapseWhitespace(string $text): string
    {
        $out = '';
        $pendingSpace = false;

        for ($i = 0, $length = strlen($text); $i < $length; $i++) {
            if (ctype_space($text[$i])) {
                $pendingSpace = $out !== '';

                continue;
            }

            if ($pendingSpace) {
                $out .= ' ';
                $pendingSpace = false;
            }

            $out .= $text[$i];
        }

        return $out;
    }

    private function canonical(): string
    {
        if ($this->isText()) {
            $text = self::collapseWhitespace(trim($this->text));

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
