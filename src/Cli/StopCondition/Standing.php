<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\StopCondition;

use JesseGall\PhpTypes\Option;

/**
 * Why a condition is not holding a stop, if it is not. There are two ways and they mean different things:
 * BLOCKED is "I cannot move this without you", which is about the work; PARKED is "this belongs to the
 * project rather than to what I promised today", which is about the list. Both keep the condition in the
 * record, and neither is `clear`.
 */
final readonly class Standing
{
    /**
     * @param  Option<string>  $blockedBecause
     */
    private function __construct(
        public Option $blockedBecause,
        public bool $parked,
    ) {}

    /**
     * Holding, as every condition does until something is said about it.
     */
    public static function holding(): self
    {
        return new self(Option::none(), false);
    }

    public static function blockedBecause(string $reason): self
    {
        return new self(Option::fromTruthy($reason), false);
    }

    /**
     * Out of the hold and still in the record — a finding worth keeping that is not this session's work.
     */
    public static function parked(): self
    {
        return new self(Option::none(), true);
    }

    public function isBlocked(): bool
    {
        return $this->blockedBecause->isSome();
    }

    /**
     * Is this holding a stop? Only what this session promised is.
     */
    public function isHolding(): bool
    {
        return ! $this->parked;
    }
}
