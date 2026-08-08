<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Expr;

use JesseGall\CodeCommandments\Vue\Keyword;
use JesseGall\CodeCommandments\Vue\Token;

/**
 * A node of parsed Vue binding expressions (JS in `:x="…"`, `v-if="…"`, `{{ … }}`). Second AST for frontend
 * detectors to reason about member chains/calls structurally, matching backend's php-parser approach. Kind-tagged node with predicates.
 */
final class Expr
{
    /**
     * The `[start, end)` byte range this expression occupies in the file it was parsed from — a
     * `.ts` module, or the SFC holding the binding. Absolute, because {@see Parser::parse} is told
     * the offset the fragment it lexes begins at, so the call inside a method inside a class
     * reports its own position rather than its statement's.
     *
     * 0/0 for an expression built by hand rather than parsed.
     */
    public private(set) int $start = 0;

    public private(set) int $end = 0;

    /**
     * @param  array<string, mixed>  $props
     */
    public function __construct(
        public readonly ExprKind $kind,
        public readonly array $props = [],
    ) {}

    /**
     * Stamp this expression with the source range it was parsed from, and return it — the parser's
     * one way to record a position. Write access is this class's alone ({@see $start}), so a node
     * cannot be moved once placed.
     */
    public function locatedAt(int $start, int $end): self
    {
        $this->start = $start;
        $this->end = $end;

        return $this;
    }

    /**
     * Every expression in this tree, this one first — the flat walk a query selects over, so a
     * selector reaches a call nested in an argument of another call without knowing the shape it
     * is buried in.
     *
     * @return list<self>
     */
    public function flatten(): array
    {
        $all = [$this];

        foreach ($this->subExpressions() as $child) {
            $all = [...$all, ...$child->flatten()];
        }

        return $all;
    }

    /**
     * The value of $key on this expression — resolve-or-THROW. Which properties a kind carries is
     * fixed by the kind, so asking for one it does not have is a programming error rather than an
     * absence; returning null for it made a typo'd key read as "not set". A property that is
     * legitimately optional is present and null, which is why {@see has} is the probe.
     */
    public function get(string $key): mixed
    {
        return array_key_exists($key, $this->props)
            ? $this->props[$key]
            : throw UnknownProperty::of($this->kind, $key, array_keys($this->props));
    }

    /**
     * Does this expression CARRY $key at all — the question to ask when the kind is not already
     * known from the branch you are in.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->props);
    }

    public function is(ExprKind $kind): bool
    {
        return $this->kind === $kind;
    }

    // ---- the shared shape vocabulary ------------------------------------------
    // These answer to the SAME names the backend's {@see \JesseGall\CodeCommandments\Ast\AstNode}
    // uses. A rule about absence, a ternary, or a guard is about a shape both languages have, so it
    // must be able to put its question to either engine — and it can only do that if the two answer
    // to one set of words. A predicate added here should be added there, and under that name.

    /**
     * Is this the literal ABSENCE — `null` or `undefined`? Both spellings are one idea, so neither
     * can be tested without the other.
     */
    public function isNullLiteral(): bool
    {
        return $this->kind === ExprKind::Literal && Keyword::isAbsence((string) $this->get('raw'));
    }

    /**
     * `a ?? b` — the absence fallback. Named as the backend names it.
     */
    public function isCoalesce(): bool
    {
        return $this->isBinaryOperator(Token::COALESCE);
    }

    /**
     * The value a `??` falls back TO, and the value it guards — the two halves an absence rule reads.
     */
    public function coalesceRight(): self
    {
        return $this->isCoalesce() ? $this->child('right') : new self(ExprKind::Unknown);
    }

    public function coalesceLeft(): self
    {
        return $this->isCoalesce() ? $this->child('left') : new self(ExprKind::Unknown);
    }

    /**
     * Is this a comparison AGAINST absence — `x === null`, `x !== undefined`? The test a null-guard
     * is written as, whichever side the literal sits on.
     */
    public function isNullComparison(): bool
    {
        if ($this->kind !== ExprKind::Binary || ! in_array((string) $this->get('op'), Token::EQUALITY, true)) {
            return false;
        }

        return $this->child('left')->isNullLiteral() || $this->child('right')->isNullLiteral();
    }

    /**
     * `a ? b : c`.
     */
    public function isTernary(): bool
    {
        return $this->kind === ExprKind::Conditional;
    }

    /**
     * A ternary whose own branches hold ANOTHER ternary — the nested conditional that wants a lookup
     * or polymorphism instead.
     */
    public function isNestedTernary(): bool
    {
        return $this->isTernary()
            && ($this->child('then')->isTernary() || $this->child('else')->isTernary());
    }

    /**
     * `a && b`, `a || b`, `a ?? b` — an operator that may not evaluate its right side.
     */
    public function isShortCircuit(): bool
    {
        return $this->kind === ExprKind::Binary
            && in_array((string) $this->get('op'), Token::SHORT_CIRCUIT, true);
    }

    /**
     * Does this expression DEFEND a value with optional chaining — `a?.b`, `a?.()`? The tell that a
     * type is being treated as possibly-absent at the point of use.
     */
    public function isOptionalChain(): bool
    {
        return $this->has('optional') && $this->get('optional') === true;
    }

    public function isCall(): bool
    {
        return $this->kind === ExprKind::Call;
    }

    /**
     * The full source of what this call CALLS — `node.closest` for `node.closest('[data-x]')`, where
     * {@see callee} answers only for a bare function name. Empty when this isn't a call.
     */
    public function callName(): string
    {
        return $this->isCall() ? $this->child('callee')->source() : '';
    }

    private function isBinaryOperator(string $operator): bool
    {
        return $this->kind === ExprKind::Binary && (string) $this->get('op') === $operator;
    }

    /**
     * The deepest DATA reach in this expression: how many property hops past the
     * root variable a chain like `data.user.firstName` makes (here, 2). A property
     * that is immediately CALLED is a method, not data, so it doesn't count
     * (`order.customer.greet()` is depth 1); arguments and sub-expressions are
     * searched too.
     *
     * $transparent names properties that are accessors, not data nesting, and so
     * don't deepen the reach — a detector passes e.g. `['value', 'length']` so a ref
     * unwrap (`x.y.value`) or a count (`x.y.length`) reads as one hop, not two.
     *
     * @param  list<string>  $transparent
     */
    public function memberDepth(array $transparent = []): int
    {
        return match ($this->kind) {
            ExprKind::Member => max($this->chainLength($transparent), $this->child('object')->memberDepth($transparent)),
            ExprKind::Index => max($this->chainLength($transparent), $this->child('object')->memberDepth($transparent), $this->child('index')->memberDepth($transparent)),
            ExprKind::Call => max($this->calleeDataDepth($transparent), $this->maxOf($this->children('arguments'), $transparent)),
            ExprKind::Unary => $this->child('argument')->memberDepth($transparent),
            ExprKind::Binary => max($this->child('left')->memberDepth($transparent), $this->child('right')->memberDepth($transparent)),
            ExprKind::Conditional => max($this->child('test')->memberDepth($transparent), $this->child('then')->memberDepth($transparent), $this->child('else')->memberDepth($transparent)),
            ExprKind::Array => $this->maxOf($this->children('elements'), $transparent),
            ExprKind::Object => $this->maxOf($this->children('values'), $transparent),
            ExprKind::Arrow => $this->child('body')->memberDepth($transparent),
            default => 0,
        };
    }

    /**
     * The ROOT identifiers this expression reads — the base of every member chain
     * (`order.customer.name` → `order`), each bare identifier, call callees and
     * arguments. The free variables a block depends on, so extracting it into a
     * component knows what to take as props.
     *
     * @return list<string>
     */
    public function roots(): array
    {
        $roots = match ($this->kind) {
            ExprKind::Identifier => [(string) $this->get('name')],
            ExprKind::Member => $this->child('object')->roots(),
            ExprKind::Index => array_merge($this->child('object')->roots(), $this->child('index')->roots()),
            ExprKind::Call => array_merge($this->child('callee')->roots(), $this->rootsOf($this->children('arguments'))),
            ExprKind::Unary => $this->child('argument')->roots(),
            ExprKind::Binary => array_merge($this->child('left')->roots(), $this->child('right')->roots()),
            ExprKind::Conditional => array_merge($this->child('test')->roots(), $this->child('then')->roots(), $this->child('else')->roots()),
            ExprKind::Array => $this->rootsOf($this->children('elements')),
            ExprKind::Object => $this->rootsOf($this->children('values')),
            ExprKind::Arrow => array_values(array_diff($this->child('body')->roots(), $this->rootsOf($this->children('params')))),
            ExprKind::Assign => array_merge($this->child('target')->roots(), $this->child('value')->roots()),
            // A literal reads nothing, and neither `for` (its own aliases are bindings, not reads)
            // nor an unparsed fragment can be walked. Named rather than defaulted: a kind that
            // holds children must say so here, or extraction silently loses the props it needs.
            ExprKind::Literal, ExprKind::For, ExprKind::Unknown => [],
        };

        return array_values(array_unique($roots));
    }

    /**
     * The bare functions this expression CALLS — `Number($e)`, `emit('x')`,
     * `clearOverride()` → `['Number','emit','clearOverride']`. These are behaviour
     * (JS globals, emits, handlers), not data: an extracted component takes data as
     * props, so a root that's merely a call callee is not one. Method calls
     * (`form.post()`) don't count — their receiver (`form`) is still data.
     *
     * @return list<string>
     */
    public function calledFunctions(): array
    {
        $names = [];
        $this->gatherCalled($names);

        return array_values(array_unique($names));
    }

    /**
     * @param  list<string>  $names
     */
    private function gatherCalled(array &$names): void
    {
        if ($this->kind === ExprKind::Call) {
            $callee = $this->props['callee'] ?? null;

            if ($callee instanceof self && $callee->kind === ExprKind::Identifier) {
                $names[] = (string) $callee->get('name');
            }
        }

        foreach ($this->subExpressions() as $child) {
            $child->gatherCalled($names);
        }
    }

    /**
     * Every member-access CHAIN as its segments — `order.customer.name` →
     * `['order','customer','name']`. The whole-path view (vs {@see roots}, just the
     * base), used to find the mid-object a deep reach should take as a prop.
     *
     * @return list<list<string>>
     */
    public function chains(): array
    {
        $chains = [];
        $this->gatherChains($chains);

        return $chains;
    }

    /**
     * This expression AS a pure member chain — `order.customer.name` → `['order','customer',
     * 'name']` — or null when it isn't one (a call, an index, an operator anywhere makes it
     * not a plain data path). The AST answer to "is this a clean property chain, and what are
     * its segments", replacing an `explode('.')` + identifier-by-identifier check.
     *
     * @return list<string>|null
     */
    public function asChain(): ?array
    {
        return match ($this->kind) {
            ExprKind::Identifier => [(string) $this->get('name')],
            ExprKind::Member => ($base = $this->child('object')->asChain()) !== null
                ? array_merge($base, [(string) $this->get('property')])
                : null,
            // Null is this method's ANSWER, not an unhandled case — an operator, a call or an
            // index anywhere means it is not a plain data path. Named so that a kind which IS
            // one has to be added here rather than inheriting "no" from a default.
            ExprKind::Literal, ExprKind::Index, ExprKind::Call, ExprKind::Unary, ExprKind::Binary,
            ExprKind::Conditional, ExprKind::Array, ExprKind::Object, ExprKind::Arrow,
            ExprKind::For, ExprKind::Assign, ExprKind::Unknown => null,
        };
    }

    /**
     * This expression AS a direct, losslessly-reconstructible call — `copyJson('nodes', id)` →
     * `['name' => 'copyJson', 'arguments' => ["'nodes'", 'id']]` — or null when it isn't one:
     * the callee must be a bare function identifier (not `obj.method(…)`), and every argument
     * must be a value {@see source} can render in full (a literal, identifier, or member/index
     * chain — NOT a nested call or operator expression, which `source()` elides as `…`). The
     * AST answer to "can this handler be forwarded verbatim as an `$emit`", instead of hand
     * re-serialising the call.
     *
     * @return array{name: string, arguments: list<string>}|null
     *
     * The bare callee name of a call — `useForm({…})` → `useForm` — or null when this isn't a
     * call on a plain identifier (a `obj.method(…)` member call has no bare callee).
     */
    public function callee(): ?string
    {
        if ($this->kind !== ExprKind::Call) {
            return null;
        }

        $callee = $this->child('callee');

        return $callee->is(ExprKind::Identifier) ? (string) $callee->get('name') : null;
    }

    /**
     * The $index-th ARGUMENT of a call, as an {@see Expr} (so a caller can inspect an object-literal
     * argument, unlike {@see asCall} which renders args to source and elides complex ones). Null when
     * this isn't a call or the argument is absent.
     */
    public function argument(int $index): ?self
    {
        return $this->kind === ExprKind::Call ? ($this->children('arguments')[$index] ?? null) : null;
    }

    /**
     * The TS SHAPE of an object literal — `{ name: '', qty: 0, tag: null }` → `{ name: string; qty:
     * number; tag: null }`. Each field takes its value's soundly-inferred type, falling back to
     * `unknown` for a value only a checker could type (a call, an identifier) — a partial shape is
     * still a usable struct. Null when this isn't an object literal or it has no static entries.
     */
    public function objectShape(): ?string
    {
        if ($this->kind !== ExprKind::Object) {
            return null;
        }

        $fields = [];

        foreach ($this->objectEntries() as $key => $value) {
            $fields[] = "{$key}: " . ($value->inferType() ?? 'unknown');
        }

        return $fields === [] ? null : '{ ' . implode('; ', $fields) . ' }';
    }

    /**
     * This expression as a CALL — null when it isn't one, or when an argument cannot be read back
     * verbatim (so nothing that forwards the call can spell it wrong).
     */
    public function asCall(): ?CallExpression
    {
        if ($this->kind !== ExprKind::Call) {
            return null;
        }

        $callee = $this->child('callee');

        if (! $callee->is(ExprKind::Identifier)) {
            return null;
        }

        $arguments = [];

        foreach ($this->children('arguments') as $argument) {
            $source = $argument->source();

            if ($source === '' || str_contains($source, '…')) {
                return null;
            }

            $arguments[] = $source;
        }

        return new CallExpression((string) $callee->get('name'), $arguments);
    }

    /**
     * The TS type this expression DENOTES when it is a literal — `false` → `boolean`,
     * `0` → `number`, `'x'` → `string`, `null`/`undefined` → themselves. Null for anything
     * that isn't a primitive literal (an identifier, call, array, object — only a real type
     * checker could resolve those). Lets the script reader type an inferred `ref(false)`
     * without a generic, instead of falling back to `unknown`.
     */
    public function literalType(): ?string
    {
        if ($this->kind !== ExprKind::Literal) {
            return null;
        }

        $raw = (string) $this->get('raw');
        $first = $raw[0] ?? '';

        return match (true) {
            $raw === 'true', $raw === 'false' => 'boolean',
            $raw === 'null' => 'null',
            $raw === 'undefined' => 'undefined',
            $first === '"', $first === "'", $first === '`' => 'string',
            is_numeric($raw) => 'number',
            default => null,
        };
    }

    /**
     * The TS type of this expression's VALUE, where it can be inferred SOUNDLY without a
     * type checker — a literal, a comparison/`!`/`typeof` (boolean/string), arithmetic
     * (number), or a logical/ternary whose branches AGREE. Null whenever an operand is
     * unknown (an identifier, call, member — we never guess what only vue-tsc could
     * resolve). A function value (arrow) has no primitive value type — see {@see returnType}.
     */
    public function inferType(): ?string
    {
        return match ($this->kind) {
            ExprKind::Literal => $this->literalType(),
            // Every operator the parser can emit, and no fallback arm: one it learns to produce
            // without a type decided here must fail rather than quietly infer nothing.
            ExprKind::Unary => match ((string) $this->get('op')) {
                '!' => 'boolean',
                'typeof' => 'string',
                '-', '+' => 'number',
            },
            ExprKind::Binary => $this->binaryType(),
            ExprKind::Conditional => $this->unionType([$this->child('then'), $this->child('else')]),
            ExprKind::Array => $this->arrayType(),
            // Deliberately un-inferred: only a type checker could resolve what these evaluate to,
            // and a wrong guess is worse than none. Named so a kind we COULD infer is not
            // silently written off the moment the parser learns to produce it.
            ExprKind::Identifier, ExprKind::Member, ExprKind::Index, ExprKind::Call,
            ExprKind::Object, ExprKind::Arrow, ExprKind::For, ExprKind::Assign,
            ExprKind::Unknown => null,
        };
    }

    /**
     * The type of an array literal — `T[]` when every element infers to the SAME primitive `T`
     * (`[50, 100, 200]` → `number[]`), the way TS widens a homogeneous literal array. Null for an
     * empty, heterogeneous, or unknown-element array — only a checker could name those.
     */
    private function arrayType(): ?string
    {
        $elements = $this->children('elements');

        if ($elements === []) {
            return null;
        }

        $element = null;

        foreach ($elements as $node) {
            $type = $node->inferType();

            if ($type === null || ($element !== null && $type !== $element)) {
                return null;
            }

            $element = $type;
        }

        return $element . '[]';
    }

    /**
     * The inferred type a callback RETURNS — an arrow's body type (`() => a === 0` →
     * `boolean`). For a `computed(() => …)` getter, this is the computed value's type.
     * Null when it isn't an arrow or the body can't be inferred soundly.
     */
    public function returnType(): ?string
    {
        return $this->kind === ExprKind::Arrow ? $this->child('body')->inferType() : null;
    }

    /**
     * The type of a binary expression: a comparison/equality is boolean, arithmetic is
     * number, and `&&`/`||`/`??` take the type of their operands when they AGREE. `+` is
     * left null — it is string concat OR addition, and only the operand types decide.
     */
    private function binaryType(): ?string
    {
        $op = (string) $this->get('op');

        return match (true) {
            in_array($op, ['===', '!==', '==', '!=', '<', '>', '<=', '>=', 'instanceof', 'in'], true) => 'boolean',
            in_array($op, ['-', '*', '/', '%', '**'], true) => 'number',
            in_array($op, ['&&', '||', '??'], true) => $this->unionType([$this->child('left'), $this->child('right')]),
            default => null,
        };
    }

    /**
     * The union of the given nodes' inferred types — `boolean | boolean` collapses to
     * `boolean`. Null if ANY operand is unknown, so a partial guess never escapes.
     *
     * @param  list<self>  $nodes
     */
    private function unionType(array $nodes): ?string
    {
        $types = [];

        foreach ($nodes as $node) {
            $type = $node->inferType();

            if ($type === null) {
                return null;
            }

            $types[$type] = true;
        }

        return $types === [] ? null : implode('|', array_keys($types));
    }

    /**
     * An object literal's `key => value` entries — `{ '@x': resolve(s, 'x'), n: 1 }` →
     * `['@x' => <call>, 'n' => <literal>]`. Spread and computed-key entries (no static key)
     * are omitted. The structural reader for a config object (Vite `resolve.alias`, a Spatie
     * map) instead of scraping it.
     *
     * @return array<string, self>
     */
    public function objectEntries(): array
    {
        if ($this->kind !== ExprKind::Object) {
            return [];
        }

        $keys = $this->props['keys'] ?? [];
        $values = $this->props['values'] ?? [];
        $entries = [];

        foreach ($values as $i => $value) {
            $key = $keys[$i] ?? null;

            if (is_string($key) && $value instanceof self) {
                $entries[$key] = $value;
            }
        }

        return $entries;
    }

    /**
     * @param  list<list<string>>  $chains
     */
    private function gatherChains(array &$chains): void
    {
        // A call's RECEIVER is data, but the method itself is not a field:
        // `order.customer.greet()` reaches the data `order.customer`, not `…greet`.
        if ($this->kind === ExprKind::Call) {
            $callee = $this->props['callee'] ?? null;

            if ($callee instanceof self) {
                $receiver = $callee->is(ExprKind::Member) || $callee->is(ExprKind::Index) ? $callee->child('object') : $callee;
                $receiver->gatherChains($chains);
            }

            foreach ($this->children('arguments') as $argument) {
                $argument->gatherChains($chains);
            }

            return;
        }

        $segments = $this->asChain();

        if ($segments !== null) {
            if (count($segments) >= 2) {
                $chains[] = $segments;
            }

            return; // a pure chain — its object is already included
        }

        foreach ($this->subExpressions() as $child) {
            $child->gatherChains($chains);
        }
    }

    /**
     * @return list<self>
     */
    private function subExpressions(): array
    {
        $children = [];

        foreach ($this->props as $value) {
            if ($value instanceof self) {
                $children[] = $value;
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if ($item instanceof self) {
                        $children[] = $item;
                    }
                }
            }
        }

        return $children;
    }

    /**
     * A reconstructed source string for this expression — enough to compare two
     * subjects (`field.type` === `field.type`). Best-effort for the shapes a switch
     * subject takes (identifiers and member chains).
     */
    public function source(): string
    {
        return match ($this->kind) {
            ExprKind::Identifier => (string) $this->get('name'),
            ExprKind::Literal => (string) $this->get('raw'),
            ExprKind::Member => $this->child('object')->source() . ($this->get('optional') ? '?.' : '.') . $this->get('property'),
            ExprKind::Index => $this->child('object')->source() . '[' . $this->child('index')->source() . ']',
            ExprKind::Call => $this->child('callee')->source() . '(…)',
            default => '',
        };
    }

    /**
     * The number of property hops from the root variable down this member/index
     * chain (a pure data chain); 0 at the root identifier, 0 for anything that isn't
     * a chain root (e.g. a call result). A {@see memberDepth} `$transparent` property
     * is a pass-through accessor and adds no hop.
     *
     * @param  list<string>  $transparent
     */
    private function chainLength(array $transparent = []): int
    {
        return match ($this->kind) {
            ExprKind::Member => $this->child('object')->chainLength($transparent) + (in_array((string) $this->get('property'), $transparent, true) ? 0 : 1),
            ExprKind::Index => $this->child('object')->chainLength($transparent) + 1,
            ExprKind::Identifier => 0,
            default => 0,
        };
    }

    /**
     * The data depth of a call's receiver — the chain the method is called ON, minus
     * the method property itself (`a.b.c()` → the data is `a.b`, depth 1).
     *
     * @param  list<string>  $transparent
     */
    private function calleeDataDepth(array $transparent = []): int
    {
        $callee = $this->child('callee');

        if ($callee->is(ExprKind::Member) || $callee->is(ExprKind::Index)) {
            return $callee->child('object')->chainLength($transparent);
        }

        return $callee->memberDepth($transparent);
    }

    private function child(string $key): self
    {
        $value = $this->props[$key] ?? null;

        return $value instanceof self ? $value : new self(ExprKind::Unknown);
    }

    /**
     * @return list<self>
     */
    private function children(string $key): array
    {
        $value = $this->props[$key] ?? [];

        return is_array($value) ? array_values(array_filter($value, static fn ($node): bool => $node instanceof self)) : [];
    }

    /**
     * @param  list<self>  $nodes
     * @param  list<string>  $transparent
     */
    private function maxOf(array $nodes, array $transparent): int
    {
        $max = 0;

        foreach ($nodes as $node) {
            $max = max($max, $node->memberDepth($transparent));
        }

        return $max;
    }

    /**
     * @param  list<self>  $nodes
     * @return list<string>
     */
    private function rootsOf(array $nodes): array
    {
        $roots = [];

        foreach ($nodes as $node) {
            $roots = array_merge($roots, $node->roots());
        }

        return $roots;
    }
}
