<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;

/**
 * A `with` — each context manager and the name it is bound to (`as`), and the block run inside them.
 */
final class With extends Node
{
    /**
     * @param  list<Expr>  $contexts
     * @param  list<?Expr>  $targets  the `as` target of each context, null where it has none
     */
    public function __construct(
        public readonly array $contexts,
        public readonly array $targets,
        public readonly Block $body,
        public readonly bool $async = false,
    ) {}

    public function children(): array
    {
        return [$this->body];
    }

    public function expressions(): array
    {
        return [...$this->contexts, ...self::present($this->targets)];
    }
}
