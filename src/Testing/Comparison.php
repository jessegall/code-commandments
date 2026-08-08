<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

/**
 * The two halves a worked example compares — the sinful code and its resolution. Either can be
 * missing: a detector with no marked scenario has neither, and one still awaiting a `#[Fixed]` twin
 * has only the first.
 */
final class Comparison
{
    public function __construct(
        public readonly ?string $bad = null,
        public readonly ?string $good = null,
    ) {}

    /**
     * The same pair with each half's docblocks lifted into comments above it.
     */
    public function lifted(): self
    {
        return new self(self::lift($this->bad), self::lift($this->good));
    }

    /**
     * The same pair showing $bad in place of its single occurrence — what a recurrence sin needs,
     * since one occurrence of a repetition demonstrates nothing.
     */
    public function withBad(string $bad): self
    {
        return new self($bad, $this->good);
    }

    public function isEmpty(): bool
    {
        return $this->bad === null && $this->good === null;
    }

    private static function lift(?string $source): ?string
    {
        return $source === null ? null : ExampleText::lifted($source);
    }
}
