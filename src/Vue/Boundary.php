<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Scribes\Span;
use JesseGall\CodeCommandments\Vue\Expr\Parser;

/**
 * Where, and whether, a chunk of template can become its own component — the one
 * reusable "extraction boundary" brain every extract detector and scribe shares
 * (duplicates, deep-reach, deep-nesting all ask the SAME questions). Wraps an element
 * in its component and answers them:
 *
 *   - {@see valid} — is this extractable at all? (not the whole fragment, has real
 *     substance, not a bare table cell that can't leave its table)
 *   - {@see root} — the NATURAL place to root the component: climb single-child
 *     wrappers to a branch point, so the component is one coherent thing
 *   - {@see name} — an intelligent name: a `v-for` list → `{Item}List`, a list item →
 *     `{Item}ListItem`, else the dominant data object it renders → `{Object}Section`
 *   - {@see props} — the free variables it needs (reads minus loop vars and called
 *     functions), the v-for iterable included
 *   - {@see contentSpan} / {@see markup} — the source to lift, unwrapping a
 *     context-bound `<template>` to its content
 *
 * Structural throughout — depth, children, expressions, never a tag-name heuristic
 * for classification (the `<ul>`/`<li>`/table checks are HTML semantics, not naming).
 */
final class Boundary
{
    private const int MIN_SUBTREE = 4; // elements — smaller isn't worth a file

    /** Elements that are only valid inside a table — extracting them breaks structure. */
    private const array TABLE_BOUND = ['td', 'th', 'tr', 'tbody', 'thead', 'tfoot', 'caption', 'colgroup'];

    private function __construct(
        public readonly Element $node,
        public readonly Sfc $sfc,
    ) {}

    public static function for(ElementMatch $match): self
    {
        return new self($match->node, $match->sfc);
    }

    public static function at(Element $node, Sfc $sfc): self
    {
        return new self($node, $sfc);
    }

    public function match(): ElementMatch
    {
        return new ElementMatch($this->node, $this->sfc);
    }

    // ---- filters --------------------------------------------------------------

    /**
     * Can this be extracted at all?
     */
    public function valid(): bool
    {
        if ($this->node->isRoot() || ! $this->node->isElement()) {
            return false;
        }

        if ($this->node->subtreeSize() < self::MIN_SUBTREE) {
            return false;
        }

        return ! in_array(strtolower($this->node->tag), self::TABLE_BOUND, true);
    }

    // ---- the natural root -----------------------------------------------------

    /**
     * The natural element to root the component at: climb up through ancestors that
     * merely WRAP this one (a single element child) until the tree branches — so the
     * component is a whole unit, not an arbitrary node mid-chain.
     */
    public function root(): self
    {
        $node = $this->node;

        while (($parent = $node->parent) !== null && $parent->isElement() && count($parent->elements()) === 1) {
            $node = $parent;
        }

        return new self($node, $this->sfc);
    }

    // ---- naming ---------------------------------------------------------------

    /**
     * An intelligent component name from what the boundary IS and SHOWS.
     */
    public function name(): string
    {
        if (($item = $this->ownLoopVar()) !== null) {
            return ucfirst($item) . 'ListItem'; // this element repeats — it's the item
        }

        if (($item = $this->childLoopVar()) !== null) {
            return ucfirst($item) . 'List'; // it contains a v-for — it's the list
        }

        if (($item = $this->ancestorLoopVar()) !== null) {
            return ucfirst($item) . 'ListItem'; // it lives inside a list item's body
        }

        if (($object = $this->dominantObject()) !== null) {
            return ucfirst($object) . 'Section';
        }

        if (($heading = $this->headingName()) !== null) {
            return $heading . 'Section'; // a static block — name it after its heading
        }

        if (($class = $this->semanticName()) !== null) {
            return $class . 'Section'; // …or its semantic (BEM) class
        }

        return $this->node->tag !== strtolower($this->node->tag) ? $this->node->tag . 'Part' : 'Section';
    }

    /**
     * A PascalCase name from the boundary's first heading text (`<h2>Advanced settings</h2>`
     * → `AdvancedSettings`), or null when it has none.
     */
    private function headingName(): ?string
    {
        foreach ([$this->node, ...$this->node->descendants()] as $element) {
            if (! in_array(strtolower($element->tag), ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
                continue;
            }

            foreach ($element->children as $child) {
                if ($child->isText() && ($name = self::pascal($child->text)) !== '') {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * A PascalCase name from the boundary's first semantic class — a BEM-ish one with a
     * `__`/`-`/`_` separator (`field-grid__row` → `FieldGridRow`); a bare utility class
     * (`flex`) is no name.
     */
    private function semanticName(): ?string
    {
        $class = strtok((string) $this->node->attribute('class'), ' ');

        if ($class === false || ! (str_contains($class, '__') || str_contains($class, '-') || str_contains($class, '_'))) {
            return null;
        }

        $name = self::pascal(str_replace(['__', '-', '_'], ' ', $class));

        return $name !== '' ? $name : null;
    }

    /**
     * Words → PascalCase, keeping only identifier characters.
     */
    private static function pascal(string $text): string
    {
        $name = '';

        foreach (preg_split('/[^A-Za-z0-9]+/', trim($text)) ?: [] as $word) {
            if ($word !== '' && ctype_alpha($word[0])) {
                $name .= ucfirst(strtolower($word));
            }
        }

        return $name;
    }

    /**
     * The data object this subtree is mostly about — the most-read non-local root.
     */
    public function dominantObject(): ?string
    {
        $counts = [];

        foreach ($this->props() as $prop) {
            $counts[$prop] = 0;
        }

        $this->each(function (Element $element) use (&$counts): void {
            foreach ($element->expressions() as $expression) {
                foreach ($expression->roots() as $root) {
                    if (isset($counts[$root])) {
                        $counts[$root]++;
                    }
                }
            }
        });

        if ($counts === []) {
            return null;
        }

        arsort($counts);

        return array_key_first($counts);
    }

    // ---- props ----------------------------------------------------------------

    /**
     * The free variables the boundary reads — its props. Reads (interpolations,
     * bindings, the v-for iterable) minus what it binds itself (loop vars) and the
     * functions it merely calls.
     *
     * @return list<string>
     */
    public function props(): array
    {
        $reads = [];
        $bound = [];
        $called = [];

        $this->each(static function (Element $element) use (&$reads, &$bound, &$called): void {
            $for = $element->attribute(Directive::For);

            foreach (self::loopVars($for) as $var) {
                $bound[] = $var;
            }

            foreach (self::loopIterable($for) as $root) {
                $reads[] = $root;
            }

            foreach ($element->expressions() as $expression) {
                $reads = array_merge($reads, $expression->roots());
                $called = array_merge($called, $expression->calledFunctions());
            }
        });

        $reads = array_filter($reads, static fn (string $root): bool => ! str_starts_with($root, '$'));

        return array_values(array_diff(array_unique($reads), $bound, $called));
    }

    // ---- markup ---------------------------------------------------------------

    /**
     * The span to lift / replace: the node itself, or — when it is a context-bound
     * `<template>` (a slot or `v-else`) — its element children (the inner content),
     * since the wrapper can't be a component root.
     */
    public function contentSpan(): Span
    {
        if (! $this->node->isContextBound()) {
            return $this->span();
        }

        $children = $this->node->elements();

        if ($children === []) {
            return $this->span();
        }

        return new Span($this->sfc->path, $this->sfc->source, $children[0]->start, $children[count($children) - 1]->end);
    }

    /**
     * The boundary's markup, re-indented for the new file — with any carried
     * structural directive ({@see carried}) stripped from the root, since it moves to
     * the call site (a `<Comp v-if>` stays where the chunk was, not inside the chunk).
     */
    public function markup(): string
    {
        $markup = $this->contentSpan()->reindent();

        foreach (array_keys($this->carried()) as $directive) {
            $markup = preg_replace('/\s+' . preg_quote($directive, '/') . '(?:\s*=\s*"[^"]*"|\s*=\s*\'[^\']*\')?/', '', $markup, 1) ?? $markup;
        }

        return $markup;
    }

    /**
     * The structural directives that must travel to the call site with the component —
     * `v-if`/`v-else-if`/`v-else`/`v-for` (and a `v-for`'s `:key`) — so a conditional
     * chain or a list keeps working. Empty for a context-bound boundary (whose wrapper,
     * and its directive, stay in place around the lifted content).
     *
     * @return array<string, string|null>
     */
    public function carried(): array
    {
        if ($this->node->isContextBound()) {
            return [];
        }

        $carried = [];

        foreach (Directive::structural() as $directive) {
            if ($this->node->hasAttribute($directive)) {
                $carried[$directive->value] = $this->node->attribute($directive);
            }
        }

        foreach (isset($carried[Directive::For->value]) ? [':key', 'key'] : [] as $key) {
            if ($this->node->hasAttribute($key)) {
                $carried[$key] = $this->node->attribute($key);

                break;
            }
        }

        return $carried;
    }

    /**
     * The variables the boundary's OWN `v-for` binds — which, when that `v-for` is
     * carried out to the call site, become props the component receives.
     *
     * @return list<string>
     */
    public function ownLoopVars(): array
    {
        return self::loopVars($this->node->attribute(Directive::For));
    }

    /**
     * The iterable a loop variable ranges over (`group.charts` for `chart`), anywhere
     * in the boundary — so the variable's type can be the iterable's element type.
     */
    public function iterableOf(string $var): ?string
    {
        foreach ([$this->node, ...$this->node->descendants()] as $element) {
            $for = $element->attribute(Directive::For);

            if ($for !== null && in_array($var, self::loopVars($for), true)) {
                $separator = str_contains($for, ' in ') ? ' in ' : ' of ';

                return trim(substr(strstr($for, $separator) ?: '', strlen($separator)));
            }
        }

        return null;
    }

    private function span(): Span
    {
        return new Span($this->sfc->path, $this->sfc->source, $this->node->start, $this->node->end);
    }

    // ---- loop / list detection ------------------------------------------------

    private function ownLoopVar(): ?string
    {
        return self::loopVars($this->node->attribute(Directive::For))[0] ?? null;
    }

    private function childLoopVar(): ?string
    {
        foreach ($this->node->descendants() as $element) {
            $vars = self::loopVars($element->attribute(Directive::For));

            if ($vars !== []) {
                return $vars[0];
            }
        }

        return null;
    }

    private function ancestorLoopVar(): ?string
    {
        for ($node = $this->node->parent; $node !== null; $node = $node->parent) {
            $vars = self::loopVars($node->attribute(Directive::For));

            if ($vars !== []) {
                return $vars[0];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function loopVars(?string $expression): array
    {
        if ($expression === null) {
            return [];
        }

        $separator = str_contains($expression, ' in ') ? ' in ' : ' of ';
        $left = strstr($expression, $separator, true) ?: '';

        $names = array_map(static fn (string $part): string => trim($part, " (){}[]\t"), explode(',', $left));

        // Keep only clean identifiers — destructured / aliased forms aren't usable names.
        return array_values(array_filter($names, static fn (string $name): bool => preg_match('/^[A-Za-z_$][\w$]*$/', $name) === 1));
    }

    /**
     * @return list<string>
     */
    private static function loopIterable(?string $expression): array
    {
        if ($expression === null) {
            return [];
        }

        $separator = str_contains($expression, ' in ') ? ' in ' : ' of ';
        $right = substr(strstr($expression, $separator) ?: '', strlen($separator));

        return $right === '' ? [] : Parser::parse($right)->roots();
    }

    /**
     * @param  \Closure(Element): void  $visit
     */
    private function each(\Closure $visit): void
    {
        $walk = static function (Element $node) use (&$walk, $visit): void {
            if ($node->isElement()) {
                $visit($node);
            }

            foreach ($node->children as $child) {
                $walk($child);
            }
        };

        $walk($this->node);
    }
}
