<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use InvalidArgumentException;

/**
 * A detector handed to the fixture verifier without declaring the fixture it is proven against.
 * Named so the failure says which detector, and what it has to implement to be verifiable.
 */
final class FixtureNotDeclared extends InvalidArgumentException
{
    public static function for(string $detector): self
    {
        return new self("{$detector} must implement " . HasFixture::class . ' to be verified against its own fixture.');
    }
}
