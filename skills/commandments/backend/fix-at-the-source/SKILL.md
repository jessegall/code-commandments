---
name: commandments-backend-fix-at-the-source
description: "Find the root cause before changing anything — trace a value to where it is born and fix it THERE, never patch the symptom. Read this FIRST whenever you are asked to refactor, fix, clean up, improve, or review any code, file, class, or namespace — and specifically before adding a null check, a `?? default`, making a field nullable, absolving a finding, tolerating bad input downstream, or when the same value gets re-checked in many places. The root-cause-first move every other style rule defers to."
---

# Fix at the source

> 🔱 **The rule above all — apply it ALWAYS.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This is that rule.

> When a value is wrong where you found it, the bug is almost always where it was **born**. Fix it there, and the symptom disappears on its own.

## The principle

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

## Rules

- [ ] Let a constructor establish what the object IS; never let building one change anything outside it.
      _Keep the collaborator as a field and act on it from the method that someone actually calls._
- [ ] Extract copy-pasted code — two functions with an identical AST must become one.
- [ ] Fix an absent value at its source; never fill a required slot with a manufactured `?? ''`/`?? 0`/`?? []`.
      _Throw a named exception at the boundary, or bake a real default into the signature._
- [ ] Hold changing state on an INSTANCE someone owns and passes; never write a static property.
      _Constructor-inject the state as a collaborator, so who holds it (and who may change it) is written down._
- [ ] Collapse type-2 clones — two functions with the same shape (differing only in names/literals) become one parameterised function.

## Worked example

### constructor-side-effect

a constructor that performs a SIDE EFFECT on a collaborator — the result thrown away, so merely building the object changes the world

```php
----------[ Bad ]----------

// Warms the export the moment anyone builds one, so merely HAVING a LedgerExport costs a request
// — and nothing in the code that constructed it asked for that.

final class LedgerExport
{
    public function __construct(private readonly HttpClient $client, private readonly string $period)
    {
        $this->client->get("/ledger/{$period}/warm");
    }

    public function body(): string
    {
        return $this->client->get("/ledger/{$this->period}");
    }
}

----------[ Good ]----------

// The same export, built for free. It holds the client and does the fetching when someone asks
// for rows, which is the moment the caller chose.

final class LazyLedgerExport
{
    public function __construct(private readonly HttpClient $client, private readonly string $period) {}

    public function body(): string
    {
        return $this->client->get("/ledger/{$this->period}");
    }
}
```

The other 4 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=backend/fix-at-the-source` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `constructor-side-effect`, `duplicate-function`, `manufactured-fake-fill`, `mutable-static-state`, `near-duplicate-function`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 5 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/absence`](../absence/SKILL.md) — deciding absence at the producer is this rule applied to the null channel.
- [`backend/exceptions`](../exceptions/SKILL.md) — surfacing a failure where it's born is this rule applied to the error channel.
- [`backend/value-objects`](../value-objects/SKILL.md) — introducing the type at the source, not threading loose data downstream.
