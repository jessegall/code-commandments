<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures;

/**
 * A minimal CompareSelf-style trait (short name `CompareSelf`, what the prophet
 * detects) for exercising the issue #31 comparison rewrite.
 */
trait CompareSelf
{
    public function equals(mixed $other): bool
    {
        return $this === $other;
    }
}
