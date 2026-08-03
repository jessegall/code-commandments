<?php

namespace Shop\Support;

/**
 * The do-nothing {@see Invokable} — the Null Object for an optional callback, so a slot that
 * "might have a callback" defaults to behaviour (a harmless no-op), never null.
 */
final class NoOp implements Invokable
{
    public function __invoke(mixed ...$arguments): mixed
    {
        return null;
    }
}
