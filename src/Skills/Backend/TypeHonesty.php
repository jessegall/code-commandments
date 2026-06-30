<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class TypeHonesty extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/type-honesty',
            tier: Tier::KeepInMind,
            order: 12,
        );
    }

    public function title(): string
    {
        return "Type honesty — the type must not lie";
    }

    public function description(): string
    {
        return "A type must tell the truth about the value. Don't fake optionality — a `?T` / nullable that the design always has set, which the code then immediately defends against (`?->`, `?? <fake>`, null-checks) or stashes as mutable scratch state and restores. The defence is the tell that the type is lying. Make the type carry the certainty: pass the value as a parameter, hold it non-nullable, or wrap per-call context in a value object. Read this BEFORE you add a nullable field set later in a method, or reach for `\$this->scratch?->… ?? false`.";
    }

    public function intro(): string
    {
        return "A `?T` that is never actually null is a lie the whole codebase pays for. Every reader has to re-prove the
value is there — `?->`, `?? <default>`, an `if (\$x === null)` — and one of those defaults silently answers
a question for a state that can't happen. Make the type say what the design guarantees.";
    }

    public function summary(): string
    {
        return "a type must not lie: don't fake optionality — a `?T` the design always has set, then defended with `?->`/`?? <fake>` or stashed as save/restore scratch state. Make the type certain (pass it, hold it non-nullable, a per-call value object). The complement of `absence`.";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
When a value is **always present where it's used**, the type should say so: a non-nullable field, a
constructor parameter, a method parameter, a value object. Hedging it as a **nullable that's set later** —
or a **mutable field used as per-call scratch** — pushes the certainty back onto every caller, who re-
establishes it with defensive code. The defensive code is the smell; the cure is upstream, in the type.

This is the complement of [`absence`](../absence/SKILL.md): *absence* says model genuine missingness
honestly (Option / empty / throw); *type-honesty* says don't manufacture missingness the design doesn't
have. A value that's truly optional belongs in `absence`. A value that's always there but typed `?T` for
convenience belongs here.

### What is NOT this sin

- **A genuinely optional, constructor-injected collaborator** read with `?->… ?? …`. If `$this->logger` is
  injected once and may legitimately be absent, defaulting it is a Null-Object choice, not a masked
  invariant — that's `absence` territory, not a type lie.
- **Modelling real missingness** — a finder that may find nothing, a config that may be unset. Use
  `absence`: `Option`, an empty default, or a throw. The lie is only when the value is *certain* and typed
  as if it weren't.

### The tell

You're re-proving, on every read, something the design already guarantees: `?->` on your own field, a
`?? <literal>` whose branch can't be reached, a `$prev = $this->x; … $this->x = $prev`. Ask: *is this value
ever actually absent here?* If no, the type is lying — move the value into the signature, a non-nullable
field, or a value object, and delete the defence.
PRINCIPLE;
    }


    public function related(): array
    {
        return [
            Absence::class => "the complement: absence models a genuine maybe-missing; this kills a FAKE one.",
            FixAtTheSource::class => "make the type certain where the value is born, not defended at every read.",
        ];
    }
}
