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
}
