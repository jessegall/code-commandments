<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\CrossFileChainBreaker;

/**
 * Negative fixture — the caller's argument isn't a simple $var reference,
 * so the tracer must bail and the prophet must fall back to the local
 * parameter hint.
 */
class ChainBreaker
{
    public function __construct(
        private readonly C $c,
    ) {}

    public function relayConditional(array $a, array $b, bool $flag): string
    {
        // Complex argument — conditional expression, not a plain variable.
        return $this->c->finalize($flag ? $a : $b);
    }
}

class C
{
    public function finalize(array $payload): string
    {
        return $payload['nodeId'];
    }
}
