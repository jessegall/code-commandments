<?php

namespace Shop\Support;

/**
 * Something that can be called. The type an optional-callback slot takes, so the slot can hold
 * BEHAVIOUR in every case — including the case where the caller supplied none.
 */
interface Invokable
{
    public function __invoke(mixed ...$arguments): mixed;
}
