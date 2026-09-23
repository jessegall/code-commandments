<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

/**
 * A `try` with its handlers, the `else` run when nothing was raised, and the `finally` always run.
 */
final class TryStmt extends Node
{
    /**
     * @param  list<ExceptHandler>  $handlers
     */
    public function __construct(
        public readonly Block $body,
        public readonly array $handlers,
        public readonly ?Block $else = null,
        public readonly ?Block $finally = null,
    ) {}

    public function children(): array
    {
        return array_values(array_filter([$this->body, ...$this->handlers, $this->else, $this->finally]));
    }
}
