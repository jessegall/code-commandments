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