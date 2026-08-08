<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

use JesseGall\PhpTypes\Option;

/**
 * A `{ … }` block — the body of a function, a branch, or a loop, as the statements it holds.
 */
final class BlockStmt extends Stmt
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
     * The statement this block ENDS with — what a rule about the happy path coming last reads. None
     * for an empty block.
     *
     * @return Option<Node>
     */
    public function last(): Option
    {
        return Option::fromNullable($this->body[count($this->body) - 1] ?? null);
    }

    public function render(): string
    {
        $body = implode("\n", array_map(static fn (Node $n): string => '    ' . $n->render(), $this->body));

        return $this->body === [] ? '{}' : "{\n{$body}\n}";
    }
}
