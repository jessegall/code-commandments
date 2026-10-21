<?php

namespace Shop\Catalog;

use RuntimeException;

/**
 * Thrown when a language was wanted and none came.
 */
final class LanguageMissing extends RuntimeException
{
    public static function wanted(): self
    {
        return new self('a language is wanted and none came');
    }
}
