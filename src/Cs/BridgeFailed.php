<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use RuntimeException;

/**
 * The Roslyn bridge did not produce trees this engine can read.
 */
final class BridgeFailed extends RuntimeException
{
    public static function toStart(): self
    {
        return new self('The Roslyn bridge could not be started.');
    }

    public static function withOutput(int $code, string $errors): self
    {
        return new self("The Roslyn bridge exited with {$code}: " . trim($errors));
    }

    public static function onVersion(mixed $version): self
    {
        return new self('The Roslyn bridge wrote output version ' . json_encode($version) . '; this engine reads version ' . Bridge::VERSION . '.');
    }
}
