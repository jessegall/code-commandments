<?php

namespace Shop\Support;

/**
 * Writes each retry attempt to the shop's log: a second thing an optional-callback slot can hold.
 */
final class LogAttempt implements Invokable
{
    public function __invoke(mixed ...$arguments): mixed
    {
        error_log('retry attempt '.count($arguments));

        return null;
    }
}
