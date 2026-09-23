<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

/**
 * A `#` comment in a Python module — the words after the `#`, and the `[start, end)` span of the whole
 * comment in the file.
 */
final readonly class Comment
{
    public function __construct(
        public string $text,
        public int $start,
        public int $end,
    ) {}

    /**
     * The comment a lexer's $token holds, placed at $base — where the tokenized source begins in its file.
     */
    public static function fromToken(Token $token, int $base): self
    {
        return new self(trim(substr($token->value, 1)), $base + $token->start, $base + $token->end);
    }
}
