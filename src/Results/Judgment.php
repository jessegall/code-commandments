<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Results;

/**
 * The result of judging a file against a commandment.
 * A file may be righteous (blessed), fallen (sinful), or have warnings (prophecies).
 */
final class Judgment
{
    /**
     * @param array<Sin> $sins Transgressions found in the file
     * @param array<Warning> $warnings Prophecies/warnings that don't fail the check
     * @param bool $skipped Whether the file was skipped (not applicable)
     * @param string|null $skipReason Reason for skipping
     */
    public function __construct(
        public readonly array $sins = [],
        public readonly array $warnings = [],
        public readonly bool $skipped = false,
        public readonly ?string $skipReason = null,
    ) {}

    /**
     * Create a righteous (passing) judgment.
     */
    public static function righteous(): self
    {
        return new self();
    }

    /**
     * Create a fallen (failing) judgment with sins.
     *
     * @param array<Sin> $sins
     */
    public static function fallen(array $sins): self
    {
        return new self(sins: $sins);
    }

    /**
     * Create a judgment with warnings (prophecies) but no sins.
     *
     * @param array<Warning> $warnings
     */
    public static function withWarnings(array $warnings): self
    {
        return new self(warnings: $warnings);
    }

    /**
     * Create a skipped judgment.
     */
    public static function skipped(string $reason): self
    {
        return new self(skipped: true, skipReason: $reason);
    }

    /**
     * Check if the judgment is righteous (no sins).
     */
    public function isRighteous(): bool
    {
        return empty($this->sins) && !$this->skipped;
    }

    /**
     * Check if the judgment found sins.
     */
    public function isFallen(): bool
    {
        return !empty($this->sins);
    }

    /**
     * Check if the judgment has warnings.
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Get the total number of sins.
     */
    public function sinCount(): int
    {
        return count($this->sins);
    }

    /**
     * Merge another judgment into this one.
     */
    public function merge(Judgment $other): self
    {
        return new self(
            sins: array_merge($this->sins, $other->sins),
            warnings: array_merge($this->warnings, $other->warnings),
            skipped: $this->skipped && $other->skipped,
            skipReason: $this->skipReason ?? $other->skipReason,
        );
    }
}
