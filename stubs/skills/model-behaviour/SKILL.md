---
name: commandments-model-behaviour
description: Put state transitions ON the record as named behaviour methods — read before writing or reviewing any code that assigns to a model's attributes and then calls save(), or any inline counter bump / status flip / multi-field update at a call site.
---

# Model behaviour — tell, don't ask

## Purpose

A record (an Eloquent model, an active-record row, any persisted entity) should
own its own state transitions. When a *caller* reaches in, pokes attributes, and
then calls `save()`, the meaning and the **invariants** of that transition leak
out of the record and scatter across every call site:

```php
// caller A
$workflow->edit_seq = $workflow->edit_seq + 1;
$workflow->save();

// caller B — the SAME transition, written again, free to drift
$workflow->edit_seq = $workflow->edit_seq + 1;
$workflow->save();
```

This is the classic **anemic-model / tell-don't-ask** smell. The fix is an
intention-revealing behaviour method that owns the change *and* its rules:

```php
// on the model
public function incrementSequenceNumber(): void
{
    $this->edit_seq++;
    $this->dispatched_at = now();   // the invariant travels WITH the transition
    $this->save();
}

// every caller
$workflow->incrementSequenceNumber();
```

One method becomes the single source of truth — readable at the call site as
*intent*, not *mechanics*, and impossible to half-apply.

## When to use this skill

Pull this skill when you are about to write, or are reviewing, any of:

- A counter bumped at a call site then saved — `$m->seq = $m->seq + 1; $m->save();`
  / `$m->count += 1; $m->save();` / `$m->version++; $m->save();`.
- A status / flag flipped then saved — `$order->status = OrderStatus::Shipped; $order->save();`.
- Several related attributes set together before a single `save()`
  (`$user->verified_at = …; $user->verification_token = null; $user->save();`) —
  a cohesive transition that wants one name.
- Any time you notice the same attribute mutated the same way in **more than one**
  place: that duplication is the strongest signal the transition belongs on the
  record.

The principle in one line: **if a write to a record ends in `save()`, ask whether
it is a named operation the record should own — if yes, move it there.**

## What to read when

| Read this reference | When you are… |
|---|---|
| `reference/transition-methods.md` | Naming and shaping the behaviour method — `mark…()` vs `transitionTo…()` vs `increment…()`, whether the method should call `save()` itself, and the few writes that are genuinely fine to leave at the call site. |

## Backs (prophet family)

This skill is the positive mirror of its enforcing prophet — a finding from it
points back here:

- **EncapsulateModelMutationProphet** — a run of attribute writes on a variable
  immediately followed by that variable's `save()` (a self-referential counter,
  an enum state flip, or a multi-field transition) at the call site.

Read the exact rule with `commandments:scripture --prophet=EncapsulateModelMutation`.
