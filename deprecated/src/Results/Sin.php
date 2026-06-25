<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Results;

/**
 * Represents a sin (violation) found in a file.
 */
final class Sin
{
    /**
     * @param  bool|null  $autoFixable  per-finding override of the prophet's
     *   SinRepenter capability: true/false forces it, null defers to the
     *   prophet (a prophet that only fixes SOME of its findings sets this).
     */
    public function __construct(
        public readonly string $message,
        public readonly ?int $line = null,
        public readonly ?int $column = null,
        public readonly ?string $snippet = null,
        public readonly ?string $suggestion = null,
        public readonly ?string $symbol = null,
        public readonly ?bool $autoFixable = null,
    ) {}

    /**
     * Create a sin with a line number.
     */
    public static function at(int $line, string $message, ?string $snippet = null, ?string $suggestion = null, ?string $symbol = null, ?bool $autoFixable = null): self
    {
        return new self(
            message: $message,
            line: $line,
            snippet: $snippet,
            suggestion: $suggestion,
            symbol: $symbol,
            autoFixable: $autoFixable,
        );
    }

    /**
     * Create a sin without a specific line number.
     */
    public static function general(string $message, ?string $suggestion = null): self
    {
        return new self(
            message: $message,
            suggestion: $suggestion,
        );
    }

}
