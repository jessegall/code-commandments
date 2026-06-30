<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Expr;

/**
 * One node of a parsed Vue binding expression — the JS inside `:x="…"`, `v-if="…"`
 * or `{{ … }}`. The template has its own AST ({@see \JesseGall\CodeCommandments\Vue\Element});
 * this is the second AST, over the JavaScript in the bindings, so a frontend
 * detector reasons about member chains and calls structurally instead of scraping
 * them with regex — the same way the backend reasons over php-parser nodes.
 *
 * A thin, kind-tagged node (like the backend's AstNode): {@see Parser} builds it,
 * predicates ({@see memberDepth}, {@see source}) sit on it.
 */
final class Expr
{
    public const string IDENTIFIER = 'identifier';
    public const string LITERAL = 'literal';
    public const string MEMBER = 'member';      // a.b / a?.b
    public const string INDEX = 'index';        // a[b]
    public const string CALL = 'call';          // f(...)
    public const string UNARY = 'unary';        // !a, -a
    public const string BINARY = 'binary';      // a === b, a || b, a + b, a ? :
    public const string CONDITIONAL = 'conditional';
    public const string ARRAY = 'array';
    public const string OBJECT = 'object';
    public const string ARROW = 'arrow';
    public const string FOR = 'for';            // v-for: aliases (in|of) iterable
    public const string UNKNOWN = 'unknown';

    /**
     * @param  array<string, mixed>  $props
     */
    public function __construct(
        public readonly string $kind,
        public readonly array $props = [],
    ) {}

    public function get(string $key): mixed
    {
        return $this->props[$key] ?? null;
    }

    public function is(string $kind): bool
    {
        return $this->kind === $kind;
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
            self::MEMBER => max($this->chainLength($transparent), $this->child('object')->memberDepth($transparent)),
            self::INDEX => max($this->chainLength($transparent), $this->child('object')->memberDepth($transparent), $this->child('index')->memberDepth($transparent)),
            self::CALL => max($this->calleeDataDepth($transparent), $this->maxOf($this->children('arguments'), $transparent)),
            self::UNARY => $this->child('argument')->memberDepth($transparent),
            self::BINARY => max($this->child('left')->memberDepth($transparent), $this->child('right')->memberDepth($transparent)),
            self::CONDITIONAL => max($this->child('test')->memberDepth($transparent), $this->child('then')->memberDepth($transparent), $this->child('else')->memberDepth($transparent)),
            self::ARRAY => $this->maxOf($this->children('elements'), $transparent),
            self::OBJECT => $this->maxOf($this->children('values'), $transparent),
            self::ARROW => $this->child('body')->memberDepth($transparent),
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
            self::IDENTIFIER => [(string) $this->get('name')],
            self::MEMBER => $this->child('object')->roots(),
            self::INDEX => array_merge($this->child('object')->roots(), $this->child('index')->roots()),
            self::CALL => array_merge($this->child('callee')->roots(), $this->rootsOf($this->children('arguments'))),
            self::UNARY => $this->child('argument')->roots(),
            self::BINARY => array_merge($this->child('left')->roots(), $this->child('right')->roots()),
            self::CONDITIONAL => array_merge($this->child('test')->roots(), $this->child('then')->roots(), $this->child('else')->roots()),
            self::ARRAY => $this->rootsOf($this->children('elements')),
            self::OBJECT => $this->rootsOf($this->children('values')),
            self::ARROW => $this->child('body')->roots(),
            default => [],
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
        if ($this->kind === self::CALL) {
            $callee = $this->props['callee'] ?? null;

            if ($callee instanceof self && $callee->kind === self::IDENTIFIER) {
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
            self::IDENTIFIER => [(string) $this->get('name')],
            self::MEMBER => ($base = $this->child('object')->asChain()) !== null
                ? array_merge($base, [(string) $this->get('property')])
                : null,
            default => null,
        };
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
        if ($this->kind !== self::LITERAL) {
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
     * @param  list<list<string>>  $chains
     */
    private function gatherChains(array &$chains): void
    {
        // A call's RECEIVER is data, but the method itself is not a field:
        // `order.customer.greet()` reaches the data `order.customer`, not `…greet`.
        if ($this->kind === self::CALL) {
            $callee = $this->props['callee'] ?? null;

            if ($callee instanceof self) {
                $receiver = $callee->is(self::MEMBER) || $callee->is(self::INDEX) ? $callee->child('object') : $callee;
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
            self::IDENTIFIER => (string) $this->get('name'),
            self::LITERAL => (string) $this->get('raw'),
            self::MEMBER => $this->child('object')->source() . ($this->get('optional') ? '?.' : '.') . $this->get('property'),
            self::INDEX => $this->child('object')->source() . '[' . $this->child('index')->source() . ']',
            self::CALL => $this->child('callee')->source() . '(…)',
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
            self::MEMBER => $this->child('object')->chainLength($transparent) + (in_array((string) $this->get('property'), $transparent, true) ? 0 : 1),
            self::INDEX => $this->child('object')->chainLength($transparent) + 1,
            self::IDENTIFIER => 0,
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

        if ($callee->is(self::MEMBER) || $callee->is(self::INDEX)) {
            return $callee->child('object')->chainLength($transparent);
        }

        return $callee->memberDepth($transparent);
    }

    private function child(string $key): self
    {
        $value = $this->props[$key] ?? null;

        return $value instanceof self ? $value : new self(self::UNKNOWN);
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
