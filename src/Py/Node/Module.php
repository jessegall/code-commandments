<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

/**
 * A whole Python file — its top-level statements.
 */
final class Module extends Node
{
    /**
     * @param  list<Node>  $body
     */
    public function __construct(public readonly array $body) {}

    public function children(): array
    {
        return $this->body;
    }
}
