<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Comment;

/**
 * A whole Python file — its top-level statements.
 */
final class Module extends Node
{
    /**
     * @param  list<Node>  $body
     * @param  list<Comment>  $comments  every `#` comment in the module, in the order written
     */
    public function __construct(
        public readonly array $body,
        public readonly array $comments = [],
    ) {}

    public function children(): array
    {
        return $this->body;
    }
}
