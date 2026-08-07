<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

/**
 * One worked example from the fixture — the sinful code and its resolution, as the skill will print
 * them. Either half can be missing: a detector with no marked scenario has neither, and one still
 * awaiting a `#[Fixed]` twin has only the first.
 */
final class Example
{
    public function __construct(
        public readonly ?string $bad = null,
        public readonly ?string $good = null,
    ) {}

    /**
     * The same example with its docblocks lifted into comments above each half — unless this skill's
     * subject IS the docblock, in which case it is left exactly where it is.
     */
    public function lifted(bool $lift): self
    {
        return $lift ? new self(self::lift($this->bad), self::lift($this->good)) : $this;
    }

    /**
     * The same example showing $bad in place of its single occurrence — what a recurrence sin needs,
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
