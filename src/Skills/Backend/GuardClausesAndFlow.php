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
            title: "Guard clauses & flow — check at the top, then go straight",
            description: "How a method body is shaped — validate preconditions at the TOP with early return/throw, keep the body flat (no if/elseif/else ladders, no deep nesting), and run the happy path last. NEVER bury a check inline (`(\$x ?? throw …)->y()`) or in a nested branch. Read this BEFORE writing a method body, a precondition/null check, an `if`, or anything that throws or returns early.",
            tagline: "Decide the unhappy paths first, at the door, and leave. What's left is the happy path, flat and
unindented. A method should read top-to-bottom: *here's what would stop us → here's the work.*",
            summary: "validate preconditions at the TOP (early return/throw), flat body, happy path last; never bury a check inline.",
            tier: Tier::Mandatory,
            order: 2,
        );
    }

    public function body(): string
    {
        return <<<'BODY'
## The principle

Every precondition a method depends on — a value that must be present, a state that must hold — is checked
**at the top** and short-circuits with a `return` or a `throw`. By the time control reaches the real work,
everything it needs is guaranteed, so the work runs at the base indentation level with no `else` and no
nesting. The shape itself documents the contract.

The opposite — burying a check inside an expression, or wrapping the happy path in `if (ok) { … }` — hides
the contract and pushes the body rightward until it's unreadable.

## When to use this skill

Reach for this the moment you are about to write:

- a **precondition / null / state check** at the start of a method;
- an `if` that decides whether the rest of the method runs;
- an `if/elseif/else` chain, or a branch nested two-deep;
- anything that **throws or returns early**.

## Rule 1 — guard at the top, never inline

Validate at the entrance with `if (…) { return; }` or `if (…) { throw …; }`. **Do not jam a check into a
larger expression**, and do not bury it in the middle of the body.

```php
// Bad — the check is smuggled into an expression; the throw is hidden mid-line
return [
    'createdAt' => ($message->created_at ?? throw new \LogicException(
        'A persisted message must carry a created_at timestamp.'
    ))->toIso8601String(),
];

// Good — the precondition is a guard at the top; the body trusts it
if ($message->created_at === null) {
    throw MissingTimestampException::forMessage($message->id);
}

return ['createdAt' => $message->created_at->toIso8601String()];
```

A bare `return $this->items[$key] ?? throw …;` as the *entire* statement of a simple lookup is fine — the
throw is the whole expression, not hidden inside one. The smell is a `?? throw` (or a `=== null ? … : …`)
**feeding further work on the same line.**

> *How* to throw — named exception, static factory — is the [`exceptions`](../exceptions/SKILL.md) skill.
> *Whether* a missing value should throw at all (vs Option / empty / a default) is
> [`absence`](../absence/SKILL.md). This skill is only about *where* the check goes: the top.

## Rule 2 — flat body: early returns, no ladders, no nesting

Once you've guarded, the body stays at one indentation level.

- **No `if/elseif/else` ladders.** Use a sequence of guards (`if (…) return/continue/throw;`), or a `match`
  for value-based dispatch.
- **No two-deep nesting.** A nested `if` is a guard waiting to be hoisted, or a block waiting to become a
  named method.
- **In a loop, guard with `continue`** instead of wrapping the body in an `if`.

```php
// Bad — nested, happy path buried, arrow-code
public function decode(Entry $entry): Action
{
    if ($entry->isValid()) {
        if ($entry->type !== null) {
            return $this->build($entry);
        }
    }
    throw UnusableEntryException::make();
}

// Good — guards out, happy path flat and last
public function decode(Entry $entry): Action
{
    if (! $entry->isValid() || $entry->type === null) {
        throw UnusableEntryException::make();
    }

    return $this->build($entry);
}
```

```php
// Bad — if/continue's inverse: the whole loop body wrapped in a condition
foreach ($entries as $entry) {
    if ($entry->isUsable()) {
        $decoded[] = $this->decode($entry);
    }
}

// Good — guard with continue, body flat
foreach ($entries as $entry) {
    if (! $entry->isUsable()) {
        continue;
    }

    $decoded[] = $this->decode($entry);
}
```

## Rule 3 — happy path last

The successful outcome is the final, unindented statement of the method — never tucked inside an `else` or
a nested `if`. If you find the happy path indented, a guard is missing.

## Checklist

```
Guard clauses & flow
- [ ] Every precondition is a guard at the TOP (early return/throw) — not inline, not buried.
- [ ] No `?? throw` / `=== null ? …` feeding further work on the same line.
- [ ] No if/elseif/else ladder; no two-deep nesting (hoist a guard or extract a method).
- [ ] Loops guard with `continue`, not a body-wrapping `if`.
- [ ] The happy path is the last, unindented statement.
```
BODY;
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
