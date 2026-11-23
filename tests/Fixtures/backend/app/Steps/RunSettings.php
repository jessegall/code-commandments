<?php

namespace Shop\Steps;

use BackedEnum;

/**
 * The settings a run was started with, read by key.
 */
final class RunSettings
{
    /**
     * @param  array<string, string>  $values
     */
    public function __construct(private readonly array $values) {}

    /**
     * @template TEnum of BackedEnum
     * @template TDefault of TEnum|null
     *
     * @param  class-string<TEnum>  $enumClass
     * @param  TDefault  $default
     * @return TEnum|TDefault
     */
    public function choice(string $key, string $enumClass, $default = null)
    {
        return $enumClass::tryFrom($this->values[$key] ?? '') ?? $default;
    }
}
