<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Exceptions;

use RuntimeException;

/**
 * A configuration file could not be located, read, or understood. Built through
 * the named factories so the throw sites stay intent-revealing.
 */
final class ConfigurationException extends RuntimeException
{
    public static function fileNotFound(string $path): self
    {
        return new self("Config file not found: {$path}");
    }

    public static function notAnArray(string $path): self
    {
        return new self("Config file must return an array: {$path}");
    }

    public static function noneFound(): self
    {
        return new self('No configuration file found. Create a commandments.php in your project root or pass --config=path.');
    }
}
