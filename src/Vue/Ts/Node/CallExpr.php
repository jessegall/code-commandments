<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A call the parser tracks structurally — a macro (`defineProps<{…}>()`, `defineEmits<…>()`,
 * `defineModel<T>('name')`), a composable (`useTaxTypes()`), or a reactive wrapper (`ref(false)`).
 * It keeps the callee, any type arguments (parsed as {@see TypeNode}s — so `defineProps`' shape is
 * a real {@see ObjectType}, never a scraped string), and the value arguments as raw source. The
 * value-argument text is enough for the string/expression facts the consumers read; deeper shape is
 * left to {@see \JesseGall\CodeCommandments\Vue\Expr\Expr} on demand.
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
    ) {}

    public function firstTypeArgument(): ?TypeNode
    {
        return $this->typeArguments[0] ?? null;
    }

    /**
     * The first argument's string literal value (unquoted), or null when it isn't a string —
     * `defineModel('open')` → `open`, `useRoute()` → null.
     */
    public function firstStringArgument(): ?string
    {
        $first = $this->arguments[0] ?? null;

        if ($first === null || ! in_array($first[0] ?? '', ['"', "'", '`'], true)) {
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
