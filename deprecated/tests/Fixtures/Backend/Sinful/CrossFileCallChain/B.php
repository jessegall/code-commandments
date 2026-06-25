<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\CrossFileCallChain;

/**
 * Middle hop — receives an array param, passes it straight through.
 */
class B
{
    public function __construct(
        private readonly C $c,
    ) {}

    public function relay(array $payload): string
    {
        return $this->c->finalize($payload);
    }
}
