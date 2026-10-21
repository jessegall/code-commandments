Every precondition a method depends on — a value that must be present, a state that must hold — is checked
**at the top** and short-circuits with a `return` or a `throw`. By the time control reaches the real work,
everything it needs is guaranteed, so the work runs at the base indentation level with no `else` and no
nesting. The shape itself documents the contract.

The opposite — burying a check inside an expression, or wrapping the happy path in `if (ok) { … }` — hides
the contract and pushes the body rightward until it's unreadable.

### Changing an array you are walking is a PHP non-problem

In Python, C# and TypeScript a walk reads the collection itself, so adding to or dropping
from it under way skips an element or never ends — each of those languages has a rule for it
here. **PHP does not, on purpose.** `foreach` over an array walks a COPY, so

```php
foreach ($lines as $line) {
    if ($line === '') {
        unset($lines[$key]);   // the walk does not notice, and finishes
    }
}
```

is confusing to read and harmless to run. Two shapes look like the fault and are not: a walk
**by reference** (`foreach ($rows as &$row)`) is how you change each ELEMENT, which is what it
is for; and a Laravel `Collection` hands its iterator a snapshot of its items, so
`$pending->forget($key)` inside `foreach ($pending as $key => …)` is the ordinary way to drain
one. Across 4,585 files of three real Laravel applications there were two by-reference walks,
both correct, and one collection drained that way, also correct. Write the loop so a reader can
see what it decides; there is no bug here to hunt.

### When to use this skill

Reach for this the moment you are about to write:

- a **precondition / null / state check** at the start of a method;
- an `if` that decides whether the rest of the method runs;
- an `if/elseif/else` chain, or a branch nested two-deep;
- anything that **throws or returns early**.