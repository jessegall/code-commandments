<?php

namespace Shop\Support\Text;

use Stringable;

/**
 * A Null Object for a piece of copy that is not there. It models absence only while a type carries the
 * OBJECT — handed to a `string` slot it renders away to nothing.
 */
final readonly class BlankText implements Stringable
{
    public function __toString(): string
    {
        return '';
    }

    /**
     * Is $value the blank — a string or a Stringable that renders to nothing?
     */
    public static function is(mixed $value): bool
    {
        return match (true) {
            is_string($value) => $value === '',
            $value instanceof Stringable => (string) $value === '',
            default => false,
        };
    }

    public static function isNot(mixed $value): bool
    {
        return ! self::is($value);
    }
}
