<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Single source of truth for the agent-facing wording of the absolution
 * policy — specifically that a SIN may be absolved single-target as a
 * deliberate, audited escape.
 *
 * This phrasing is surfaced in several places (the session-start briefing,
 * the pilgrimage walk, `judge --next`). Keep it here so updating the policy
 * means editing ONE string, not hunting every echo of it.
 */
final class AbsolutionPolicy
{
    /**
     * The canonical sin-absolution rule, as prose. `$absolveCmd` is the
     * absolve invocation to show (e.g. `commandments absolve --at=path:line
     * --reason="…"`), so the sentence carries the exact command in context.
     */
    public static function sinEscapeRule(string $absolveCmd): string
    {
        return "Sins are imperative: FIXING is the default. A sin CAN be absolved "
            . "single-target WITH A REASON ({$absolveCmd}) when it is pre-existing / "
            . 'out-of-scope code you must not touch — a deliberate, audited escape so '
            . 'you are never wedged, never a shortcut. Batch absolve never sweeps a sin.';
    }

    /**
     * The short, command-free form for tight contexts (a numbered CLI step,
     * a table cell) where the command is shown separately.
     */
    public static function sinEscapeShort(): string
    {
        return 'a pre-existing / out-of-scope sin you must NOT touch can be absolved '
            . 'WITH A REASON — a deliberate, audited escape, never a shortcut; FIX stays the default';
    }

    /**
     * "You own every sin you encounter" — a sin is yours to handle whether you
     * wrote it or merely touched the file. Shared by every briefing surface.
     */
    public static function ownEverySinRule(): string
    {
        return 'A sin is a sin regardless of who wrote it. If judge surfaces a sin — in '
            . 'your own changes OR pre-existing in a file you are working in — you handle '
            . 'it: fix it, or — for a pre-existing/out-of-scope sin you must not touch, or '
            . 'an advisory warning whose rubric genuinely does not apply — absolve it with '
            . 'a reason (a sin absolution is deliberate and audited). "I didn\'t cause this" '
            . 'is NEVER a reason to leave a finding unhandled. Be a gentleman: leave every '
            . 'file you touch righteous.';
    }
}
