<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Results;

/**
 * Represents a sin (violation) found in a file.
 */
final class Sin
{
    public function __construct(
        public readonly string $message,
        public readonly ?int $line = null,
        public readonly ?int $column = null,
        public readonly ?string $snippet = null,
        public readonly ?string $suggestion = null,
    ) {}

    /**
     * Create a sin with a line number.
     */
    public static function at(int $line, string $message, ?string $snippet = null, ?string $suggestion = null): self
    {
        return new self(
            message: $message,
            line: $line,
            snippet: $snippet,
            suggestion: $suggestion,
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

    /**
     * Get a formatted string representation.
     */
    public function format(string $filePath): string
    {
        $location = $this->line !== null ? ":{$this->line}" : '';
        $result = "{$filePath}{$location}: {$this->message}";

        if ($this->snippet !== null) {
            $result .= "\n    > {$this->snippet}";
        }

        if ($this->suggestion !== null) {
            $result .= "\n    Suggestion: {$this->suggestion}";
        }

        return $result;
    }
}
