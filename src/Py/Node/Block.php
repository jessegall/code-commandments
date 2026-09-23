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
}
