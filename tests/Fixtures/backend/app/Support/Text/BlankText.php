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
}
