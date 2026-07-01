<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class GuardClausesAndFlow extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/guard-clauses-and-flow',
            tier: Tier::Mandatory,
            order: 2,
        );
    }

    public function title(): string
    {
        return "Guard clauses & flow — check at the top, then go straight";
    }

    public function trigger(): string
    {
        return "How a method body is shaped — validate preconditions at the TOP with early return/throw, keep the body flat (no if/elseif/else ladders, no deep nesting), and run the happy path last. NEVER bury a check inline (`(\$x ?? throw …)->y()`) or in a nested branch. Read this BEFORE writing a method body, a precondition/null check, an `if`, or anything that throws or returns early.";
    }

    public function intro(): string
    {
        return "Decide the unhappy paths first, at the door, and leave. What's left is the happy path, flat and
unindented. A method should read top-to-bottom: *here's what would stop us → here's the work.*";
    }

    public function summary(): string
    {
        return "validate preconditions at the TOP (early return/throw), flat body, happy path last; never bury a check inline.";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
Every precondition a method depends on — a value that must be present, a state that must hold — is checked
**at the top** and short-circuits with a `return` or a `throw`. By the time control reaches the real work,
everything it needs is guaranteed, so the work runs at the base indentation level with no `else` and no
nesting. The shape itself documents the contract.

The opposite — burying a check inside an expression, or wrapping the happy path in `if (ok) { … }` — hides
the contract and pushes the body rightward until it's unreadable.

### When to use this skill

Reach for this the moment you are about to write:

- a **precondition / null / state check** at the start of a method;
- an `if` that decides whether the rest of the method runs;
- an `if/elseif/else` chain, or a branch nested two-deep;
- anything that **throws or returns early**.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            Exceptions::class => "*how* a guard throws (named factory, never a message string).",
            Absence::class => "*whether* a missing value is a guard-and-throw at all, vs Option / empty / default.",
            FixAtTheSource::class => "if every caller re-guards the same value, the guard belongs upstream where the value is born.",
        ];
    }
}
