<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

/**
 * The statements a compound statement owns — the indented suite after its `:`, or the simple
 * statements written on that same line.
 */
final class Block extends Node
{
    /**
     * @param  list<Node>  $body
     */
    public function __construct(public readonly array $body = []) {}

    public function children(): array
    {
        return $this->body;
    }

    /**
     * The statements of this block, a string left standing on its own — a docstring — aside.
     *
     * @return list<Node>
     */
    public function statementsBeyondText(): array
    {
        return array_values(array_filter($this->body, static fn (Node $statement): bool => ! ($statement instanceof ExprStmt && $statement->isBareString())));
    }
}
