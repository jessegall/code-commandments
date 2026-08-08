<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Span;
use JesseGall\CodeCommandments\Ts\Expr\Expr;
use JesseGall\CodeCommandments\Ts\Expr\ExprKind;
use JesseGall\CodeCommandments\Ts\Expr\Parser;
use JesseGall\PhpTypes\Option;

/**
 * Determines extraction boundaries for template chunks: whether extractable, natural root,
 * intelligent naming, required props, and source span.
 */
final class Boundary
{

    /**
     * Elements that are only valid inside a table — extracting them breaks structure.
     */
    private const array TABLE_BOUND = ['td', 'th', 'tr', 'tbody', 'thead', 'tfoot', 'caption', 'colgroup'];

    /**
     * JS globals a template expression may reference; never props.
     */
    private const array JS_GLOBALS = [
        'Object', 'Array', 'Math', 'JSON', 'Number', 'String', 'Boolean', 'Date', 'RegExp', 'Map', 'Set',
        'Symbol', 'Promise', 'console', 'window', 'document', 'globalThis', 'NaN', 'Infinity', 'undefined',
        'parseInt', 'parseFloat', 'isNaN', 'isFinite', 'encodeURIComponent', 'decodeURIComponent',
    ];

    /**
     * @var array{edits: list<array{int, int, string}>, events: array<string, int>, safe: bool}|null
     */
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

        // Substantial: real content AND internal structure, the shared definition across all extractors.
        if (! $this->node->substantial()) {
            return false;
        }

        return ! in_array(strtolower($this->node->tag), self::TABLE_BOUND, true);
    }

    // ---- the natural root -----------------------------------------------------

    /**
     * The natural element to root the component at: climb up through ancestors that
     * merely WRAP this one (a single element child) until the tree branches — so the
     * boundary lands on the whole unit rather than a node mid-chain.
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
        foreach ($this->node->loopVar() as $item) {
            return ucfirst($item) . 'ListItem'; // this element repeats — it's the item
        }

        foreach ($this->childLoopVar() as $item) {
            return ucfirst($item) . 'List'; // it contains a v-for — it's the list
        }

        foreach ($this->ancestorLoopVar() as $item) {
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
        $classes = $this->node->attribute('class');

        if ($classes->isNone()) {
            return null;
        }

        $class = strtok($classes->unwrap(), ' ');

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
            foreach ($element->loopVars() as $var) {
                $bound[] = $var;
            }

            foreach ($element->loopIterable() as $iterable) {
                $reads = array_merge($reads, $iterable->roots());
            }

            foreach ($element->expressions() as $expression) {
                $reads = array_merge($reads, $expression->roots());
                $called = array_merge($called, $expression->calledFunctions());
            }
        });

        $reads = array_filter($reads, static fn (string $root): bool => ! str_starts_with($root, '$'));

        // A prop is reactive state the parent passes down — NOT a JS global (`Object.keys(…)`) and
        // NOT an imported name (a component/util/constant the child imports itself). Excluding these
        // stops the extraction minting bogus `Object`/`AUTO_ANIMATE_QUICK` props typed `unknown`.
        $props = array_values(array_diff(array_unique($reads), $bound, $called, self::JS_GLOBALS, $this->importedNames()));

        return $this->unwrapPropsVariable($props);
    }

    /**
     * The `defineProps` result variable is not itself a prop — its MEMBERS are. A chunk that reads
     * `props.favicon`/`props.label` should forward `favicon`/`label` (each typed from the parent's
     * props), not a bogus `props` prop typed `unknown`. Swap the props-variable root for the members
     * the chunk actually accesses on it. (The scribe strips the `props.` prefix from the markup, and
     * binds each member with {@see callSiteExpression}.)
     *
     * @param  list<string>  $props
     * @return list<string>
     */
    private function unwrapPropsVariable(array $props): array
    {
        $variable = $this->propsVariable();

        if ($variable === null || ! in_array($variable, $props, true)) {
            return $props;
        }

        return array_values(array_unique([...array_diff($props, [$variable]), ...$this->propsVariableMembers()]));
    }

    /**
     * The `const props = defineProps(…)` binding name, or null when the component captures none.
     */
    public function propsVariable(): ?string
    {
        return new Script($this->sfc->scriptContent())->propsVariable();
    }

    /**
     * The members this chunk reads off the props-variable — `props.label` / `props.favicon` →
     * `['label', 'favicon']`. These are the real props such a chunk forwards.
     *
     * @return list<string>
     */
    public function propsVariableMembers(): array
    {
        $variable = $this->propsVariable();

        if ($variable === null) {
            return [];
        }

        $members = [];

        $this->each(static function (Element $element) use (&$members, $variable): void {
            foreach ($element->expressions() as $expression) {
                foreach ($expression->chains() as $chain) {
                    if (($chain[0] ?? null) === $variable && isset($chain[1])) {
                        $members[] = $chain[1];
                    }
                }
            }
        });

        return array_values(array_unique($members));
    }

    /**
     * The expression to bind a prop to at the CALL SITE, in the parent's scope — a props-member is
     * bound `props.member` (the parent has no bare `member`), any other prop by its own name.
     */
    public function callSiteExpression(string $prop): string
    {
        return in_array($prop, $this->propsVariableMembers(), true) ? "{$this->propsVariable()}.{$prop}" : $prop;
    }

    /**
     * The names imported into this component's `<script setup>` — a child that references one imports
     * it itself (the scribe carries the import), so it is never forwarded as a prop.
     *
     * @return list<string>
     */
    private function importedNames(): array
    {
        $names = [];

        foreach (new Script($this->sfc->scriptContent())->imports() as $import) {
            $names = [...$names, ...$import->names];
        }

        return $names;
    }

    /**
     * Does the lifted chunk render `<slot>`s? If so the chunk consumes slots from the host,
     * and the call site must FORWARD the host's slots to the new component — otherwise the
     * slotted bodies render empty (the extracted component's `<slot>`s get nothing).
     */
    public function hasSlots(): bool
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
                if ($expression->is(ExprKind::Assign)) {
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

        foreach ($this->carried() as $carried) {
            $attribute = $this->node->attributeSpan($carried->name)->filter(
                static fn (array $at): bool => $at[0] >= $span->start && $at[1] <= $span->end,
            );

            foreach ($attribute as [$start, $end]) {
                $edits[] = [...Element::removalSpan($span->source, $span->start, $span->end, $start, $end), ''];
            }
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

                if ($call === null || ! in_array($call->name, $locals, true)) {
                    continue;
                }

                // A handler calling the component's OWN emit (`emit('save')`) is itself an emit,
                // not a forwardable function — rewriting it would mint an event literally named
                // `emit`. Leave it, so the reach/rewrite mismatch refuses the extraction (a clean
                // emit-reforward is a future enhancement).
                if ($call->name === $emit) {
                    continue;
                }

                foreach ($element->attributeSpan($name) as [$start, $end]) {
                    $edits[] = [$start, $end, "{$name}=\"\$emit('{$call->name}'{$call->trailingArguments()})\""];

                    // An event declares the MOST arguments any handler passes it, so the first
                    // handler seen sets the arity and a wider one raises it.
                    if (! isset($events[$call->name]) || $events[$call->name] < $call->arity()) {
                        $events[$call->name] = $call->arity();
                    }

                    $rewrites++;
                }
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
     * @return list<Attribute>
     */
    public function carried(): array
    {
        return $this->node->isContextBound() ? [] : $this->node->carriedDirectives();
    }

    /**
     * Does the boundary's own `v-for` travel to the call site? Then the loop variables it
     * used to bind become props the extracted component receives.
     */
    public function hasCarriedLoop(): bool
    {
        return Attribute::anyNamed($this->carried(), Directive::For);
    }

    /**
     * The variables the boundary's OWN `v-for` binds — which, when that `v-for` is
     * carried out to the call site, become props the component receives.
     *
     * @return list<string>
     */
    public function ownLoopVars(): array
    {
        return $this->node->loopVars();
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
            if (! $element->loopVar()->isSomeAnd(static fn (string $alias): bool => $alias === $var)) {
                continue;
            }

            return $element->loopIterable()->unwrap()->source();
        }

        return null;
    }

    private function span(): Span
    {
        return new Span($this->sfc->path, $this->sfc->source, $this->node->start, $this->node->end);
    }

    // ---- loop / list detection ------------------------------------------------

    /**
     * The element variable of the ONE loop somewhere below this boundary — none when it
     * holds no loop, and none when it holds several: a section that HAPPENS to contain
     * lists is not itself "a list", so naming it `{firstLoopVar}List` would be wrong (and
     * could collide with a child it renders).
     *
     * @return Option<string>
     */
    private function childLoopVar(): Option
    {
        $found = Option::none();

        foreach ($this->node->descendants() as $element) {
            $var = $element->loopVar();

            if ($var->isNone()) {
                continue;
            }

            if ($found->isSome()) {
                return Option::none();
            }

            $found = $var;
        }

        return $found;
    }

    /**
     * The element variable of the nearest loop this boundary sits INSIDE — so a chunk
     * lifted out of a list item's body is still named for the item.
     *
     * @return Option<string>
     */
    private function ancestorLoopVar(): Option
    {
        foreach ($this->node->ancestors() as $ancestor) {
            $var = $ancestor->loopVar();

            if ($var->isSome()) {
                return $var;
            }
        }

        return Option::none();
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
