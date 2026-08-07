<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Vue\Expr\Expr;
use JesseGall\CodeCommandments\Vue\Expr\Interpolation;
use JesseGall\CodeCommandments\Vue\Expr\Parser;
use JesseGall\PhpTypes\Option;

/**
 * Template node (element, text, or fragment root) with raw directive names. Thin data
 * node; fluent queries and file:line sit above it.
 */
class Element
{
    /**
     * A real component carries CONTENT (this many elements) …
     */
    protected const int MIN_COMPONENT_ELEMENTS = 6;

    /**
     * … AND its own internal STRUCTURE (this many levels — not a flat wrapper).
     */
    protected const int MIN_COMPONENT_DEPTH = 3;

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
     * splices to remove a directive. None when it isn't present / wasn't lexed with a span.
     *
     * @return Option<array{int, int}>
     */
    public function attributeSpan(string|Directive $name): Option
    {
        return Option::fromNullable($this->attributeSpans[Directive::attributeName($name)] ?? null);
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
        $edits = [];

        foreach ($names as $name) {
            $span = $this->attributeSpan($name)->filter(
                static fn (array $span): bool => $span[0] >= $from && $span[1] <= $to,
            );

            foreach ($span as [$start, $end]) {
                $edits[] = [...self::removalSpan($source, $from, $to, $start, $end), ''];
            }
        }

        return self::spliceSource($source, $from, $to, $edits);
    }

    /**
     * The span to cut when removing an attribute: it swallows the space/tab before the
     * attribute (so no `<div  >` gap), and — when the attribute sat ALONE on its line — its
     * trailing newline too (so no blank line is left). An inline attribute keeps its line.
     *
     * @return array{int, int}
     */
    public static function removalSpan(string $source, int $from, int $to, int $start, int $end): array
    {
        while ($start > $from && ($source[$start - 1] === ' ' || $source[$start - 1] === "\t")) {
            $start--;
        }

        if ($start === $from || $source[$start - 1] === "\n") {
            $scan = $end;
            while ($scan < $to && ($source[$scan] === ' ' || $source[$scan] === "\t")) {
                $scan++;
            }

            if ($scan < $to && $source[$scan] === "\n") {
                $end = $scan + 1;
            }
        }

        return [$start, $end];
    }

    /**
     * The slice `[$from, $to)` of $source with a set of `[start, end, replacement]` edits
     * applied — the write engine's splicer: cut (replacement `''`) or rewrite (any text) a
     * span by its KNOWN offsets, never a regex. Applied end-first so earlier offsets stay
     * valid. Edits must not overlap.
     *
     * @param  list<array{int, int, string}>  $edits
     */
    public static function spliceSource(string $source, int $from, int $to, array $edits): string
    {
        usort($edits, static fn (array $a, array $b): int => $b[0] <=> $a[0]);

        $text = substr($source, $from, $to - $from);

        foreach ($edits as [$start, $end, $replacement]) {
            $text = substr($text, 0, $start - $from) . $replacement . substr($text, $end - $from);
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
        return array_key_exists(Directive::attributeName($name), $this->attributes);
    }

    /**
     * The VALUE of attribute $name — none when the element doesn't carry it, and none
     * when it carries it without one (`v-else`, `disabled`), because a valueless
     * attribute has no value to read. Presence is the other question: {@see hasAttribute}.
     *
     * @return Option<string>
     */
    public function attribute(string|Directive $name): Option
    {
        return Option::fromNullable($this->attributes[Directive::attributeName($name)] ?? null);
    }

    /**
     * This element's own attribute $name, as the name/value pair a writer renders — none
     * when it doesn't carry it at all.
     *
     * @return Option<Attribute>
     */
    public function namedAttribute(string|Directive $name): Option
    {
        $key = Directive::attributeName($name);

        return $this->hasAttribute($key)
            ? Option::some(new Attribute($key, $this->attribute($key)))
            : Option::none();
    }

    /**
     * The directives that must travel with this element when its content is lifted
     * somewhere else — the structural ones it carries (`v-if`/`v-else-if`/`v-else`/
     * `v-for`) plus, for a loop, its `:key`. Whoever moves the content renders these at
     * the new call site, so the branch or list keeps working.
     *
     * @return list<Attribute>
     */
    public function carriedDirectives(): array
    {
        $carried = [];

        foreach (Directive::structural() as $directive) {
            foreach ($this->namedAttribute($directive) as $attribute) {
                $carried[] = $attribute;
            }
        }

        foreach ($this->hasAttribute(Directive::For) ? [':key', 'key'] : [] as $key) {
            foreach ($this->namedAttribute($key) as $attribute) {
                $carried[] = $attribute;

                break 2;
            }
        }

        return $carried;
    }

    /**
     * This element's own `v-for`, parsed — none when it isn't a loop. The one place the
     * directive is read, so no caller threads its raw expression around.
     *
     * @return Option<Expr>
     */
    public function loop(): Option
    {
        return $this->attribute(Directive::For)->map(static fn (string $for) => Parser::parseFor($for));
    }

    /**
     * The variables this element's own `v-for` binds — empty when it isn't a loop.
     *
     * @return list<string>
     */
    public function loopVars(): array
    {
        return $this->loop()->mapOr([], static fn (Expr $loop): array => $loop->get('aliases'));
    }

    /**
     * The ELEMENT variable of this element's own `v-for` — its first alias. In
     * `(item, index) in list` the later aliases are the index / object key, not members,
     * so only the first ranges over the iterable's element type.
     *
     * @return Option<string>
     */
    public function loopVar(): Option
    {
        return Option::fromNullable($this->loopVars()[0] ?? null);
    }

    /**
     * The expression this element's own `v-for` ranges over — none when it isn't a loop.
     *
     * @return Option<Expr>
     */
    public function loopIterable(): Option
    {
        return $this->loop()->map(static fn (Expr $loop): Expr => $loop->get('iterable'));
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

    /**
     * The EVENT handlers this element binds, each to its parsed expression, keyed by the RAW
     * attribute name — `@click="save()"` / `v-on:submit="go"` → `['@click' => <save()>,
     * 'v-on:submit' => <go>]`. The raw name (not the event) is the key so the caller can find
     * the handler's {@see attributeSpan} to rewrite it. The `@`/`v-on:` sibling of
     * {@see propBindings}.
     *
     * @return array<string, Expr>
     */
    public function eventBindings(): array
    {
        $bindings = [];

        foreach ($this->attributes as $name => $value) {
            if ($value !== null && (str_starts_with($name, '@') || str_starts_with($name, 'v-on:'))) {
                $bindings[$name] = Parser::parse($value);
            }
        }

        return $bindings;
    }

    /**
     * A kebab attribute name as its camelCase prop — `order-table` → `orderTable`. No regex.
     */
    protected static function camelize(string $name): string
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
     * Is this element the DIRECT child of a `<Transition>`/`<TransitionGroup>`? Vue's
     * transition components track and animate their real element children by key — the
     * structural directive (`v-if`, `v-for`) MUST sit on the element itself; hoisting it
     * to a `<template>` renders a fragment and breaks child tracking / FLIP moves.
     * Control flow here is the Vue canon, not a sin.
     */
    public function isTransitionChild(): bool
    {
        return $this->parent !== null
            && in_array($this->parent->tag, ['Transition', 'TransitionGroup', 'transition', 'transition-group'], true);
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
     * Every element ABOVE this one, innermost first, up to the fragment root — the climb
     * "what am I inside of?", the mirror of {@see descendants}.
     *
     * @return iterable<Element>
     */
    public function ancestors(): iterable
    {
        for ($ancestor = $this->parent; $ancestor !== null; $ancestor = $ancestor->parent) {
            yield $ancestor;
        }
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
        $node = $this;

        while ($node !== null) {
            $spine[] = $node;
            $node = $node->parent;
        }

        return $spine;
    }

    /**
     * How deeply this element is nested — its own level counting from the top (a
     * top-level element is 1). The fragment root and text/comment ancestors don't count.
     */
    public function depth(): int
    {
        return count(array_filter($this->ancestry(), static fn (self $node): bool => $node->isElement()));
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

    protected function shape(): string
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
    protected static function collapseWhitespace(string $text): string
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

    protected function canonical(): string
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
