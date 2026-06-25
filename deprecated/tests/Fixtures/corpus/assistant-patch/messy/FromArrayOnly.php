<?php

namespace App\Support;

/**
 * Forces Spatie Data ::from() to take an array (no magic object dispatch);
 * convert objects in explicit fromX() factories.
 *
 * @code-commandments-generated
 */
trait FromArrayOnly
{
    public static function from(mixed ...$payloads): static
    {
        foreach ($payloads as $payload) {
            assert(
                is_array($payload),
                static::class . '::from() expects an array — write an explicit fromX() factory that converts first.'
            );
        }

        return parent::from(...$payloads);
    }
}
