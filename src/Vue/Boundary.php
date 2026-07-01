<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Scribes\Span;
use JesseGall\CodeCommandments\Vue\Expr\Expr;
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

    /** Elements that are only valid inside a table — extracting them breaks structure. */
    private const array TABLE_BOUND = ['td', 'th', 'tr', 'tbody', 'thead', 'tfoot', 'caption', 'colgroup'];

    /** @var array{edits: list<array{int, int, string}>, events: array<string, int>, safe: bool}|null */
    private ?array $emits = null;

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

        // Substantial enough to be a component — real content AND its own internal structure,
        // never a thin/flat wrapper. One definition, shared with the other extractors.
        if (! $this->node->substantial()) {
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

        foreach (self::words($text) as $word) {
            if (ctype_alpha($word[0])) {
                $name .= ucfirst(strtolower($word));
            }
        }

        return $name;
    }

    /**
     * Split text into its alphanumeric words, char by char — the PascalCase tokenizer
     * (no regex over the text).
     *
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $words = [];
        $current = '';

        for ($i = 0, $length = strlen($text); $i < $length; $i++) {
            if (ctype_alnum($text[$i])) {
                $current .= $text[$i];
            } elseif ($current !== '') {
                $words[] = $current;
                $current = '';
            }
        }

        if ($current !== '') {
            $words[] = $current;
        }

        return $words;
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

    /**
     * Does the lifted chunk render `<slot>`s? If so the chunk consumes slots from the host,
     * and the call site must FORWARD the host's slots to the new component — otherwise the
     * slotted bodies render empty (the extracted component's `<slot>`s get nothing).
     */
    public function rendersSlots(): bool
    {
        return $this->node->renders('slot');
    }

    /**
     * The values the boundary WRITES — two-way state, not read-only input. A value is written
     * when it is `v-model[:arg]="x"`-bound, OR assigned in a handler (`@click="x = true"`).
     * Lifted out, each such value must become a `defineModel` (bound with `v-model` at the call
     * site), never a plain prop: Vue forbids writing a prop, so a `v-model` on one fails the
     * build and an assignment is a silent no-op. The root identifier of each write.
     *
     * @return list<string>
     */
    public function models(): array
    {
        $models = [];

        $this->each(static function (Element $element) use (&$models): void {
            // `v-model[:arg]="x"` — a two-way binding.
            foreach ($element->directiveBindings(Directive::Model) as $expression) {
                foreach (Parser::parse($expression)->roots() as $root) {
                    $models[] = $root;
                }
            }

            // `@event="x = …"` — a handler assigning the value (the readonly-prop trap, #256).
            foreach ($element->expressions() as $expression) {
                if ($expression->is(Expr::ASSIGN)) {
                    foreach ($expression->get('target')->roots() as $root) {
                        $models[] = $root;
                    }
                }
            }
        });

        return array_values(array_unique($models));
    }

    // ---- markup ---------------------------------------------------------------

    /**
     * The span to lift / replace: the node itself, or — when it is a context-bound
     * `<template>` (a slot or `v-else`) — its element children (the inner content),
     * since the wrapper can't be a component root.
     */
    public function contentSpan(): Span
    {
        // A `<template>` (slot, v-if/v-for, or bare) is a fragment wrapper — never a valid
        // component root. Lift its CONTENT; its directives ride out to the call site.
        if (! $this->node->isTemplate()) {
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
        // The markup edits, all by KNOWN span (the AST write engine, no regex): rewrite each
        // handler call to a parent function into an `$emit` ({@see emits}), and drop the carried
        // directives. (For a <template> boundary the directives sit on the wrapper, OUTSIDE the
        // content span, so they're already gone.)
        $span = $this->contentSpan();
        $edits = array_values(array_filter(
            $this->emits()['edits'],
            static fn (array $edit): bool => $edit[0] >= $span->start && $edit[1] <= $span->end,
        ));

        foreach (array_keys($this->carried()) as $name) {
            $attribute = $this->node->attributeSpan($name);

            if ($attribute === null || $attribute[0] < $span->start || $attribute[1] > $span->end) {
                continue;
            }

            $edits[] = [...Element::removalSpan($span->source, $span->start, $span->end, $attribute[0], $attribute[1]), ''];
        }

        $spliced = Element::spliceSource($span->source, $span->start, $span->end, $edits);

        return Span::reindentText($spliced, $span->column());
    }

    // ---- emit-up (handler calls to parent functions) --------------------------

    /**
     * Can this boundary be lifted WITHOUT breaking? A handler that CALLS a parent-local
     * function (`@click="copyJson('nodes')"`) can be re-expressed as an `$emit` the parent
     * listens for, but a parent function reached any OTHER way — a `:prop` binding, a `{{ }}`
     * interpolation, a multi-statement handler — would dangle as undefined in the child. False
     * when any such non-forwardable reach exists, so the scribe refuses rather than emit a
     * silent no-op.
     */
    public function extractable(): bool
    {
        return $this->emits()['safe'];
    }

    /**
     * The events the lifted child must `defineEmits`, each to its arity — `copyJson('a', b)` →
     * `['copyJson' => 2]`. The parent listens for each (`@copy-json="copyJson"`) so the lifted
     * handler call reaches its original function again.
     *
     * @return array<string, int>
     */
    public function emitEvents(): array
    {
        return $this->emits()['events'];
    }

    /**
     * The plan for forwarding handler calls to PARENT-local functions as emits — they can't
     * ride into the child (the function is defined in the parent `<script setup>`, undefined in
     * the lifted component). Each clean handler call becomes an `$emit`: `@click="copyJson('a')"`
     * → `@click="$emit('copyJson', 'a')"` in the child, `defineEmits<{ copyJson: […] }>()` there,
     * `@copy-json="copyJson"` at the call site. Returns the markup `edits` (handler span → emit),
     * the `events` to declare (name → arity), and `safe` — false when a parent function is
     * reached by anything other than a cleanly-forwardable handler.
     *
     * @return array{edits: list<array{int, int, string}>, events: array<string, int>, safe: bool}
     */
    private function emits(): array
    {
        if ($this->emits !== null) {
            return $this->emits;
        }

        $script = new Script($this->sfc->scriptContent());
        $locals = $script->localNames();
        $emit = $script->emitName();
        $edits = [];
        $events = [];
        $rewrites = 0;
        $reached = 0;

        $this->each(function (Element $element) use (&$edits, &$events, &$rewrites, &$reached, $locals, $emit): void {
            // Every expression that reaches a parent local — handlers, bindings, text.
            foreach ($element->expressions() as $expression) {
                if (array_intersect($expression->calledFunctions(), $locals) !== []) {
                    $reached++;
                }
            }

            // The clean event handlers among them — a direct call to a parent function — are
            // the ones we can forward as an emit.
            foreach ($element->eventBindings() as $name => $expression) {
                $call = $expression->asCall();

                if ($call === null || ! in_array($call['name'], $locals, true)) {
                    continue;
                }

                // A handler calling the component's OWN emit (`emit('save')`) is itself an emit,
                // not a forwardable function — rewriting it would mint an event literally named
                // `emit`. Leave it, so the reach/rewrite mismatch refuses the extraction (a clean
                // emit-reforward is a future enhancement).
                if ($call['name'] === $emit) {
                    continue;
                }

                $span = $element->attributeSpan($name);

                if ($span === null) {
                    continue;
                }

                $arguments = $call['arguments'] === [] ? '' : ', '.implode(', ', $call['arguments']);
                $edits[] = [$span[0], $span[1], "{$name}=\"\$emit('{$call['name']}'{$arguments})\""];
                $events[$call['name']] = max($events[$call['name']] ?? 0, count($call['arguments']));
                $rewrites++;
            }
        });

        // Safe only when EVERY parent-local reach is one of those clean handler rewrites; any
        // other route would leave an undefined reference in the child.
        return $this->emits = ['edits' => $edits, 'events' => $events, 'safe' => $rewrites === $reached];
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
     * in the boundary — so the variable's type can be the iterable's element type. Only
     * the FIRST alias is an element: in `(item, index) in list` the 2nd/3rd aliases are
     * the numeric index / object key, not members, so they don't take the element type.
     */
    public function iterableOf(string $var): ?string
    {
        foreach ([$this->node, ...$this->node->descendants()] as $element) {
            $for = $element->attribute(Directive::For);

            if ($for !== null && (self::loopVars($for)[0] ?? null) === $var) {
                return Parser::parseFor($for)->get('iterable')->source();
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
        $found = null;

        foreach ($this->node->descendants() as $element) {
            $vars = self::loopVars($element->attribute(Directive::For));

            if ($vars === []) {
                continue;
            }

            // More than one loop → this is a section/dialog that HAPPENS to contain lists,
            // not itself "a list". Naming it `{firstLoopVar}List` is wrong (and can collide
            // with a child it renders). Fall through to a structural name.
            if ($found !== null) {
                return null;
            }

            $found = $vars[0];
        }

        return $found;
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

        return Parser::parseFor($expression)->get('aliases');
    }

    /**
     * @return list<string>
     */
    private static function loopIterable(?string $expression): array
    {
        if ($expression === null) {
            return [];
        }

        return Parser::parseFor($expression)->get('iterable')->roots();
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
