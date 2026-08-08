<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Language;

/**
 * One worked example from the fixture — the before/after {@see Comparison} the skill will print, and
 * the {@see Language} it is written in.
 */
final class Example
{
    public function __construct(
        public readonly Comparison $code = new Comparison(),
        public readonly Language $language = Language::Php,
    ) {}

    public function bad(): ?string
    {
        return $this->code->bad;
    }

    public function good(): ?string
    {
        return $this->code->good;
    }

    /**
     * The same example with its docblocks lifted into comments above each half — unless this skill's
     * subject IS the docblock, in which case it is left exactly where it is.
     */
    public function lifted(bool $lift): self
    {
        return $lift ? new self($this->code->lifted(), $this->language) : $this;
    }

    /**
     * The same example showing $bad in place of its single occurrence — what a recurrence sin needs,
     * since one occurrence of a repetition demonstrates nothing.
     */
    public function withBad(string $bad): self
    {
        return new self($this->code->withBad($bad), $this->language);
    }

    /**
     * The same example, told which language its fixture was written in.
     */
    public function in(Language $language): self
    {
        return new self($this->code, $language);
    }

    public function isEmpty(): bool
    {
        return $this->code->isEmpty();
    }
}
