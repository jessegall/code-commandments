<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class Exceptions extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/exceptions',
            tier: Tier::KeepInMind,
            order: 8,
        );
    }

    public function title(): string
    {
        return "Exceptions — fail hard, fix once";
    }

    public function description(): string
    {
        return "How to fail — throw NAMED exceptions via static factories (`Thing::for(\$x)`), never a message string at the throw site, and never swallow a failure into null/false/[]/Option::none(). Read this FIRST whenever you write a `throw`, a `try`/`catch`, an exception class, or are deciding what to do when something goes wrong. Fail hard and named, at the source.";
    }

    public function intro(): string
    {
        return "**Fail hard, fix once** beats *fail gracefully, debug forever.* A loud, named, contextual failure is a
five-minute fix. A swallowed one is a silent wrong result you chase for a week.";
    }

    public function summary(): string
    {
        return "throwing or catching: named `::for()` factory exceptions, never swallow a failure.";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
A failure is information. The instant it happens, it knows the most it will ever know — *what* broke and
*with what values*. Throw that knowledge **loudly, by type, at the source**. Every line you put between
the failure and its surfacing — a `catch` that returns null, a default that papers over it, a bare
`Exception("...")` — destroys information and moves the eventual debugging session further from the cause.

This is [`fix-at-the-source`](../fix-at-the-source/SKILL.md) for the error channel — and the place the
[`absence`](../absence/SKILL.md) skill sends you when "missing" turns out to be a broken state.

### The one place you tolerate: a named outer boundary

Fail-hard does **not** mean every layer rethrows forever. It means failures travel *up* to **one explicit
boundary** that is allowed to absorb them — and even there, absorbing is **observable**, never silent. The
canonical shape: an untrusted-input decoder that catches per item, **logs**, and skips, then fails hard if
*nothing* survived.

Inside the system, invariants throw. At the *one* untrusted edge, you catch-log-skip. That's fail-hard
*and* resilient — not graceful-and-silent.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            FixAtTheSource::class => "surface the failure where it's born.",
            Absence::class => "absence routes \"missing = broken state\" here for the *how* of throwing; this skill routes \"swallowed failure became an empty value\" back there as the inverse smell.",
        ];
    }
}
