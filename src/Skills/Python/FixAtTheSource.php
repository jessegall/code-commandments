<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Python;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class FixAtTheSource extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'python/fix-at-the-source',
            tier: Tier::Mandatory,
            order: 34,
        );
    }

    public function title(): string
    {
        return 'Python fix at the source — where a value is born, not where it hurts';
    }

    public function trigger(): string
    {
        return "Writing an `__init__` that calls out to something it was handed, a module-level or class-level variable that functions write to, or a fix for a Python finding that is tempting to patch where it surfaced. Read this BEFORE making a constructor do work, keeping state in a `global` or a class attribute, or adding a check at a call site, and when a `python-constructor-side-effect` or `python-mutable-static-state` finding points here.";
    }

    public function intro(): string
    {
        return "A wrong value is almost always wrong where it was born. The call site that trips over it is only
where it surfaced; patching there adds a check, the next caller trips again, and the origin never
learns. In Python the same move applies to objects and state: build an object without changing the
world, and keep changing state on an instance someone owns, so every effect has a place you can see.";
    }

    public function summary(): string
    {
        return 'trace a value, an effect or a piece of state to where it starts, and fix it there.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
### Trace it upstream before you change a line

A finding is a symptom. Before adding an `if x is None`, a default or a `try` where it surfaced, ask
where the value came from and walk back until you reach the place that made it. Fix that place, and
the check you were about to write — and every copy of it — is no longer needed.

### An `__init__` builds; it does not act

A constructor says what the object IS: it stores what it was given and derives what it needs. When it
tells a collaborator to DO something — warm a cache, register itself, open a connection — and throws
the answer away, merely creating the object changes the world, in a line of code that reads like
bookkeeping. Keep the collaborator as an attribute and act on it from the method someone calls, at the
moment they chose.

### State that changes lives on an instance

A module-level variable written by a `global`, or a class attribute assigned from a method, is state
every caller shares and nobody passes. Who changed it, and when, is written nowhere. Hold changing
state on an instance, pass that instance to the code that needs it, and the dependency is in the
signature where a reader can see it.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource::class => 'the same discipline over PHP, where every other skill defers to it.',
            Absence::class => 'deciding absence where a value is born is this rule applied to `None`.',
        ];
    }

    public function languages(): array
    {
        return [Language::Python];
    }
}
