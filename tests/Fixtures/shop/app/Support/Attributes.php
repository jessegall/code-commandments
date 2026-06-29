<?php

namespace Shop\Support;

/**
 * A dynamic, caller-defined attribute map (keys are not a fixed schema).
 */
final class Attributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function value(array $attributes, string $key): mixed
    {
        return $attributes[$key] ?? null;
    }
}
