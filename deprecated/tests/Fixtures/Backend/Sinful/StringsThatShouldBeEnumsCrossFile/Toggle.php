<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\StringsThatShouldBeEnumsCrossFile;

/**
 * The "no enum exists yet" scenario for Pattern 3. There is no `Mode`
 * enum in the scroll — but call sites form a closed set of two
 * literals that look like case names. The prophet should still flag
 * the param and suggest creating an enum.
 */
class Toggle
{
    public function flip(string $mode): void
    {
        // intentionally empty
    }
}
