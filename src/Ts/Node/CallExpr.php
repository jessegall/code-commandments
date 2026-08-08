<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts\Node;

use JesseGall\CodeCommandments\Ts\Expr\Expr;
use JesseGall\PhpTypes\Option;

/**
 * A call the parser tracks structurally — a macro (`defineProps<{…}>()`, `defineEmits<…>()`,
 * `defineModel<T>('name')`), a composable (`useTaxTypes()`), or a reactive wrapper (`ref(false)`).
 * It keeps the callee, any type arguments (parsed as {@see TypeNode}s — so `defineProps`' shape is
 * a real {@see ObjectType}, never a scraped string), and the value arguments as raw source. The
 * value-argument text is enough for the string/expression facts the consumers read; deeper shape is
 * left to {@see \JesseGall\CodeCommandments\Ts\Expr\Expr} on demand.
 */
final class CallExpr extends Node
{
    /**
     * @param  list<TypeNode>  $typeArguments
     * @param  list<string>  $arguments  raw source of each value argument
     */
    public function __construct(
        public readonly string $callee,
        public readonly array $typeArguments = [],
        public readonly array $arguments = [],
        public readonly ?Expr $expression = null,
    ) {}

    /**
     * The call as a parsed expression, when it stands as a statement — so a rule about calls reaches
     * a bare `track(event)` the same way it reaches one nested in an argument.
     */
    public function expressions(): array
    {
        return $this->expression !== null ? [$this->expression] : [];
    }

    public function callTo(string $callee): ?self
    {
        return $this->callee === $callee ? $this : null;
    }

    /**
     * The call's first TYPE argument — the `T` of `defineProps<T>()`. None when it has none, which
     * is the ordinary case for a call that is not a typed macro.
     *
     * @return Option<TypeNode>
     */
    public function firstTypeArgument(): Option
    {
        return Option::fromNullable($this->typeArguments[0] ?? null);
    }

    /**
     * Does this call's FIRST argument begin with $prefix — the test behind "is this a
     * `withDefaults(defineProps(…), …)`?". A call with no arguments begins with nothing, which
     * is the call's own business to know rather than its reader's.
     */
    public function firstArgumentStartsWith(string $prefix): bool
    {
        $first = $this->arguments[0] ?? null;

        return $first !== null && str_starts_with($first, $prefix);
    }

    /**
     * The first argument's string literal value (unquoted), or null when it isn't a string —
     * `defineModel('open')` → `open`, `useRoute()` → null.
     */
    public function firstStringArgument(): ?string
    {
        $first = $this->arguments[0] ?? null;

        if ($first === null || $first === '' || ! in_array($first[0], ['"', "'", '`'], true)) {
            return null;
        }

        return substr($first, 1, -1);
    }

    public function render(): string
    {
        $types = $this->typeArguments === []
            ? ''
            : '<' . implode(', ', array_map(static fn (TypeNode $t): string => $t->render(), $this->typeArguments)) . '>';

        return $this->callee . $types . '(' . implode(', ', $this->arguments) . ')';
    }
}
