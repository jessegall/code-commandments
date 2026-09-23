<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

/**
 * A `#` comment in a Python module — the words after the `#`, and the `[start, end)` span of the whole
 * comment in the file. Its body keeps the indentation after the marker (`#` or Sphinx's `#:`), which is
 * how a run of comments nests one line under another.
 */
final readonly class Comment
{
    public function __construct(
        public string $text,
        public int $start,
        public int $end,
        public string $body = '',
    ) {}

    /**
     * The comment a lexer's $token holds, placed at $base — where the tokenized source begins in its file.
     */
    public static function fromToken(Token $token, int $base): self
    {
        $after = substr($token->value, 1);

        return new self(trim($after), $base + $token->start, $base + $token->end, rtrim((string) preg_replace('/^:? ?/', '', $after)));
    }
}
