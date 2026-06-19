# Example 05 — invariant vs genuine absence, side by side (the negative test)

**Domain:** a user directory with two lookups that look similar but mean opposite things.

This is the example that proves the system **does not over-correct**. Two methods:

- `getById(int $id)` — the id is a foreign key / session id. A miss is a **bug** → invariant → must throw.
- `findByEmail(string $email)` — the email is free-text **search input**. A miss is **normal** → genuine
  absence → `Option<User>` is the right model, and `PreferOptionOverNullProphet` is **correct** here.

The `initial/` also has a hand-rolled `MaybeUser` wrapper that exists *only* to cope with `getById`
returning null — once the contract is fixed it becomes dead and is removed.

## What must be flagged, and in what order

**`UserDirectory::getById()`** (`?? null`, nullable, FK invariant):
- **ROOT CAUSE → `RegistryReturnContractProphet`** (lookup that must resolve → throw + `has()`).
- **SYMPTOM → `PreferOptionOverNullProphet` / `NoNullCoalesceToNullProphet`** → deferred / annotated.
- Fix: `getById(int $id): User` throws.

**`UserDirectory::findByEmail()`** (returns `null` today):
- **SYMPTOM → `PreferOptionOverNullProphet`** fires.
- The **root-cause trigger runs** (`ThrowOnUnhandledCase`, `RegistryReturnContract`, `PreferTotalOverNullable`,
  `NoSwallowedNotFound`) and **matches nothing** — a search-by-email is genuinely optional.
- ⇒ **No root cause, no deferral, no hint.** `PreferOptionOverNull` stands on its own and is the correct
  guidance. Fix: `findByEmail(string $email): Option` (an `Option<User>`).

**`MaybeUser`** + `GreetingController`: the wrapper is a half-`Option` that only exists because `getById`
returned null. Dead after the fix.

## The fix (`final/`)

- `getById` → returns `User`, **throws** `UserNotFoundException` (invariant enforced).
- `findByEmail` → returns **`Option<User>`** and the caller uses `->map(...)->getOr('not found')` (genuine
  absence, modelled correctly — this is `Option<T>` doing its actual job).
- **Class change: REMOVE a class** — delete `MaybeUser`; it was pure ceremony born from the bad nullable
  contract.

> Takeaway: the same `null` shape gets **opposite** fixes depending on whether the absence is an invariant
> violation or a real possibility. The root-cause system is what lets the linter tell them apart instead
> of blindly pushing every `null` toward `Option`.
