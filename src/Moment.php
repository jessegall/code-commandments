<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * The three moments of a plan's life the {@see PlanExecution} profile hangs checks on, and the
 * token the `commandments checks <moment>` command selects one by. {@see Start} runs once before
 * the first phase, {@see Phase} after each phase, and {@see Complete} at the very end — the only
 * moment that {@see appendsJudge appends `judge --branch`}, so a plan can never finish unjudged.
 */
enum Moment: string
{
    case Start = 'start';
    case Phase = 'phase';
    case Complete = 'complete';

    /**
     * The command-line token for this moment (`commandments checks <token>`).
     */
    public function token(): string
    {
        return $this->value;
    }

    /**
     * Resolve the token a user typed to a moment, defaulting to {@see Complete} — the end gate is
     * the common case, so a bare `commandments checks` runs it.
     */
    public static function fromToken(?string $token): self
    {
        return self::tryFrom((string) $token) ?? self::Complete;
    }

    /**
     * Does this moment append `judge --branch` after its declared checks? Only {@see Complete}
     * does — the end gate always judges the whole branch; the earlier moments stay fast.
     */
    public function appendsJudge(): bool
    {
        return $this === self::Complete;
    }
}
