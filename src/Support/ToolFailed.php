<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use RuntimeException;

/**
 * A tool this package runs beside itself — a bridge to another language's compiler — did not answer as its
 * contract says.
 */
final class ToolFailed extends RuntimeException
{
    public static function toStart(string $tool): self
    {
        return new self("The {$tool} could not be started.");
    }

    public static function withOutput(string $tool, int $code, string $errors): self
    {
        return new self("The {$tool} exited with {$code}: " . trim($errors));
    }

    public static function onVersion(string $tool, mixed $version, int $reads): self
    {
        return new self("The {$tool} wrote output version " . json_encode($version) . "; this engine reads version {$reads}.");
    }
}
