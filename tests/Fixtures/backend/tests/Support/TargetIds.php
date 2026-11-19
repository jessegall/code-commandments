<?php

namespace Shop\Tests\Support;

/**
 * Fixed target ids for tests, numbered so a test reads which target it means.
 */
final class TargetIds
{
    public static function of(int $number): string
    {
        return sprintf('target-%04d', $number);
    }
}
