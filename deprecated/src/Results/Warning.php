<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Results;

/**
 * Represents a warning (prophecy) found in a file.
 * Warnings don't fail the check but should be reviewed.
 */
final class Warning
{
    public function __construct(
        public readonly string $message,
        public readonly ?int $line = null,
        public readonly ?string $snippet = null,
        public readonly ?string $symbol = null,
        public readonly ?bool $autoFixable = null,
    ) {}

    /**
     * Create a warning with a line number.
     */
    public static function at(int $line, string $message, ?string $snippet = null, ?string $symbol = null, ?bool $autoFixable = null): self
    {
        return new self(
            message: $message,
            line: $line,
            snippet: $snippet,
            symbol: $symbol,
            autoFixable: $autoFixable,
        );
    }

    /**
     * Create a warning without a specific line number.
     */
    public static function general(string $message): self
    {
        return new self(message: $message);
    }

}
