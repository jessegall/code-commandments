<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class FixAtTheSource extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/fix-at-the-source',
            tier: Tier::Mandatory,
            order: 1,
        );
    }

    public function title(): string
    {
        return "Fix at the source";
    }

    public function trigger(): string
    {
        return "Find the root cause before changing anything — trace a value to where it is born and fix it THERE, never patch the symptom. Read this FIRST whenever you are asked to refactor, fix, clean up, improve, or review any code, file, class, or namespace — and specifically before adding a null check, a `?? default`, making a field nullable, absolving a finding, tolerating bad input downstream, or when the same value gets re-checked in many places. The root-cause-first move every other style rule defers to.";
    }

    public function intro(): string
    {
        return "When a value is wrong where you found it, the bug is almost always where it was **born**. Fix it there, and the symptom disappears on its own.";
    }

    public function summary(): string
    {
        return "the root-cause-first move: trace a value to where it's born, never patch the symptom. Governs how every change is made.";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
A symptom is a null check, a `?? default`, a "make this field nullable", an `absolve`, a re-validation
you keep writing in handler after handler. The temptation is to silence it *where you see it*. Don't.
Every symptom is a value that arrived already wrong — under-typed, half-parsed, maybe-absent — because
the place that **produced** it didn't finish its job.

Three ideas, one move:

- **Parse, don't validate.** A boundary's job is to turn loose input into a *precise* value. If it hands
  downstream code something it must re-check, it parsed nothing — it just relabelled the looseness.
- **Make illegal states unrepresentable.** Once a value is parsed into the right type, the invalid case
  cannot be constructed, so no downstream code can be tempted to handle it.
- **Validate once, at the edge; flow total values inward.** Past the boundary, every value is whole.
  Handlers, strategies, services receive things that *cannot* be wrong — so they hold zero null checks.

The downstream null check is not the problem to solve. It is the *evidence* pointing back at the
problem, which lives upstream.

### STOP — check this before you plan a single edit

Before you change anything, test your plan against these trip-wires. **If any is true, you are about to
patch a symptom — stop and trace upstream instead:**

- Your plan touches **more than one** place that uses the same value (e.g. tidying the null-checks in
  several strategies / handlers / consumers). **Repeated validation of one value means its producer
  handed it over under-typed — fix the producer, and all those edits evaporate.**
- Your plan **adds** a guard, a nullable field, a `?? default`, or a try/catch to make something pass.
- You are **enumerating** problems to fix one-by-one, instead of naming the *one place the value is born*.

**A fix at the source almost always DELETES code** — a redundant "raw" type, a pile of repeated guards —
it rarely adds. If your diff is mostly *additions* of defensive code, you are patching, not fixing.

Reduce your plan to **one origin change**. If you can't, you haven't found the source yet — keep tracing.
Do **not** start editing consumers.

### When to use this skill

Reach for this the moment you are about to:

- add a `=== null` / `is_null()` / `?->` guard, or a `?? <default>` / `?? ''`, to make something "work";
- make a field **nullable** so a check passes, or add a default to a constructor slot;
- `absolve` / suppress / "tolerate" a finding instead of fixing it;
- write the *same* validation in a second handler/strategy/consumer of the same value;
- refactor a decoder, parser, mapper, hydrator, or any boundary that turns raw input into objects.

If a rule from another skill (absence, guards, exceptions, enums) tempts you to patch a consumer —
**come here first.**

### The move: trace upstream before you fix

1. **Name the symptom.** What are you about to write? (a null check, a default, a nullable field, an
   absolve, a repeated guard). Say it out loud.
2. **Find where the value is born.** Walk *upstream* to its origin — the boundary that parsed it, the
   method that returned it, the DTO it was hydrated into. Follow it as far back as it stays loose.
3. **Ask the only question that matters at the origin:** *could this value have been made total here?*
   Could the boundary have parsed it into a type where the case you're guarding cannot exist?
4. **Fix at the origin.** Make the boundary throw on missing-required, parse into the precise type, or
   stop producing the loose shape at all. The downstream symptom vanishes — delete it.
5. **Only if the value is *genuinely* optional at its source** does a field stay nullable — and that is
   a decision made *at the source, by its real contract*, never a reflex downstream. "Optional" is a
   property of the domain, not a way to make a check go quiet.

### Worked example: a boundary that defers its job

A decoder turns an inbound command into typed actions. The "raw" DTO makes **every** field optional, so
it validates nothing — and every handler re-derives what it actually needs.

**The symptom-patch (what NOT to do):** make `MoveCommand::$label` nullable so the `?? ''` can go, or
add more `?? default`s, or `absolve` the finding. Each makes the type lie *more* and pushes the question
further downstream. `RawCommand` stays — the phantom that started it all.

**Fix at the source:** parse the raw input into the *total* command at the boundary. Required fields are
read through accessors that throw *here*; the phantom DTO is deleted; handlers receive whole values.

Now nothing downstream of the decoder holds a null check. The validation happens once, where the value
is born, and every `Command` that exists is real.

### Fixes that aren't fixes

- **Making a required field nullable to silence a check.** "Display-only" does not mean "optional" — a
  label shown on a card can be *required*. Whether a field is nullable is decided by its source's
  contract, not by what makes a downstream check pass.
- **`?? default` / `?? ''` into a required slot.** Manufactures a value that looks valid and isn't, and
  *drops the absence signal* so the real problem is now invisible.
- **Absolving / tolerating the symptom.** You've agreed the wrongness is fine to keep. It isn't.
- **Per-field nullability triage on an all-optional DTO** ("which of these do I make non-null?"). Wrong
  question. The question is: *should this DTO exist, and where is this value actually parsed?*
- **A wrapper / override / cast / suppression that launders N call sites so they pass.** When a cluster of
  call sites trips a check, fixing them *is* the work — do not give a class a looser constructor that
  quietly coerces the input (e.g. a `ValueBag` that accepts a looser key type to silence ~10 type errors),
  or scatter casts/`@phpstan-ignore`, to avoid touching them. That makes the code *pass* while growing it
  and hides the smell at the source. Fix the call sites — even the awkward one.
PRINCIPLE;
    }


    public function related(): array
    {
        return [
            Absence::class => "deciding absence at the producer is this rule applied to the null channel.",
            Exceptions::class => "surfacing a failure where it's born is this rule applied to the error channel.",
            ValueObjects::class => "introducing the type at the source, not threading loose data downstream.",
        ];
    }
}
