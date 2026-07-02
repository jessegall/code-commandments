<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * An array-destructuring pattern — `const [a, b] = pair`. Binds its element names in order; a hole
 * (`[, b]`) is a null element and binds nothing.
 */
final class ArrayPattern extends Pattern
{
    /**
     * @param  list<?string>  $elements  each bound local, or null for a skipped position
     */
    public function __construct(public readonly array $elements) {}

    public function names(): array
    {
        return array_values(array_filter($this->elements, static fn (?string $n): bool => $n !== null));
    }

    public function render(): string
    {
        return '[' . implode(', ', array_map(static fn (?string $n): string => $n ?? '', $this->elements)) . ']';
    }
}
