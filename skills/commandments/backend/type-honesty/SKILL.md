---
name: type-honesty
description: A type must tell the truth about the value. Don't fake optionality — a `?T` / nullable that the design always has set, which the code then immediately defends against (`?->`, `?? <fake>`, null-checks) or stashes as mutable scratch state and restores. The defence is the tell that the type is lying. Make the type carry the certainty: pass the value as a parameter, hold it non-nullable, or wrap per-call context in a value object. Read this BEFORE you add a nullable field set later in a method, or reach for `$this->scratch?->… ?? false`.
---

# Type honesty — the type must not lie

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A `?T` that is never actually null is a lie the whole codebase pays for. Every reader has to re-prove the
> value is there — `?->`, `?? <default>`, an `if ($x === null)` — and one of those defaults silently answers
> a question for a state that can't happen. Make the type say what the design guarantees.

## The principle

When a value is **always present where it's used**, the type should say so: a non-nullable field, a
constructor parameter, a method parameter, a value object. Hedging it as a **nullable that's set later** —
or a **mutable field used as per-call scratch** — pushes the certainty back onto every caller, who re-
establishes it with defensive code. The defensive code is the smell; the cure is upstream, in the type.

This is the complement of [`absence`](../absence/SKILL.md): *absence* says model genuine missingness
honestly (Option / empty / throw); *type-honesty* says don't manufacture missingness the design doesn't
have. A value that's truly optional belongs in `absence`. A value that's always there but typed `?T` for
convenience belongs here.

## The sins

### 1. A masked invariant — `$this->scratch?->… ?? <fake>`

A nullable field that's set inside the operation, then read through `?->` with a fake default. The default
answers for the impossible "not set yet" case — and answers **wrong**.

```php
// Bad — currentInstance is set for the whole compile; the ?? false answers a state
// that can't happen, and quietly classifies a control handle as data flow.
private WorkflowInstance | null $currentInstance = null;

private function isControlHandle(WorkflowNode $node, string $port): bool
{
    return $this->currentInstance?->isBodyHandle($node->id, $port) ?? false;
}
```

```php
// Good — the value is certain in scope, so type it certain: take it as a parameter
// (or a per-call context object). No `?->`, no fake default — and the compiler proves
// it's there.
private function isControlHandle(WorkflowInstance $instance, WorkflowNode $node, string $port): bool
{
    return $instance->isBodyHandle($node->id, $port);
}
```

### 2. Scratch state on `$this` — the save/restore dance

Per-call data stashed on the object and restored afterwards. The field only "works" mid-call; its type
can't express that, so the method has to save the old value and put it back.

```php
// Bad — currentWorkflow / currentInstance are this call's inputs, smuggled through fields.
$previous = $this->currentInstance;
try {
    $this->currentInstance = WorkflowInstance::build(...);
    // … work that reads $this->currentInstance …
} finally {
    $this->currentInstance = $previous;          // put it back so re-entrant calls survive
}
```

```php
// Good — a method's inputs belong in its signature, not on $this. Pass the instance
// (or a CompileScope value object) down. No fields to save, restore, or null-guard;
// re-entrancy is free.
$scope = new CompileScope($instance, $workflow, $graph);
$this->emitNodes($scope, $ordered);
```

## What is NOT this sin

- **A genuinely optional, constructor-injected collaborator** read with `?->… ?? …`. If `$this->logger` is
  injected once and may legitimately be absent, defaulting it is a Null-Object choice, not a masked
  invariant — that's `absence` territory, not a type lie.
- **Modelling real missingness** — a finder that may find nothing, a config that may be unset. Use
  `absence`: `Option`, an empty default, or a throw. The lie is only when the value is *certain* and typed
  as if it weren't.

## The tell

You're re-proving, on every read, something the design already guarantees: `?->` on your own field, a
`?? <literal>` whose branch can't be reached, a `$prev = $this->x; … $this->x = $prev`. Ask: *is this value
ever actually absent here?* If no, the type is lying — move the value into the signature, a non-nullable
field, or a value object, and delete the defence.
