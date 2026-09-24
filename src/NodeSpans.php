<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * The spans a {@see ParsedModule} reports, read off the nodes it walked — TypeScript's and Python's the
 * same way.
 */
trait NodeSpans
{
    /**
     * @return list<SyntaxNode>
     */
    abstract public function nodes(): array;

    /**
     * @return list<array{0: int, 1: int}>
     */
    public function nodeSpans(): array
    {
        return array_map(static fn (SyntaxNode $node) => [$node->start, $node->end], $this->nodes());
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    public function functionSpans(): array
    {
        return array_values(array_map(
            static fn (SyntaxNode $node) => [$node->start, $node->end],
            array_filter($this->nodes(), static fn (SyntaxNode $node): bool => $node->functionBody()->isSome()),
        ));
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    public function typeSpans(): array
    {
        return array_values(array_map(
            static fn (SyntaxNode $node) => [$node->start, $node->end],
            array_filter($this->nodes(), static fn (SyntaxNode $node): bool => $node->isTypeDeclaration()),
        ));
    }
}
