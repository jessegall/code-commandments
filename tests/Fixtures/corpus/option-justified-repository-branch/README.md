# option-justified-repository-branch

**Verdict: `Option<Customer>` is the better design here.** The two call sites that
consume `CustomerRepository::findByEmail` both treat "not found" as a *meaningful
domain branch*, not an incidental "not set" — so forcing explicit handling earns
its keep and prevents a real "used a null by accident" bug.

## Why the call sites justify Option

This is exactly the case the corpus background calls out: absence is **juggled by
several callers** and the value is **threaded through transforms**.

- **`RegisterOrderService::placeOrder` — create-or-update.** Absence is not "no
  value"; it's the trigger to *provision a brand-new customer*. With Option the
  branch is the expression itself:
  `findByEmail($email)->getOrElse(fn () => $this->provisionCustomer(...))`. The
  type makes it impossible to reach `$customer->id` without having decided what
  absence means. In the messy version the same logic is a hand-rolled
  `if ($x === null)` that a careless edit can skip — `$customer->id` straight
  after the bare call compiles fine and only explodes at runtime. That is the
  precise bug this slice is about.

- **`SendStatementService::buildStatement` — fallback-to-error + transform chain.**
  The found customer is mapped to a `Recipient`, then to a `Statement`. Option
  chains `->map(...)->map(...)->getOrThrow(...)` with no intermediate null check,
  and `getOrThrow` makes "no such customer" an explicit, un-skippable domain
  failure. The nullable version must re-prove presence with a stacked guard
  before each step, and a stray `?->` would quietly build a half-null
  `Recipient` instead of failing loudly.

Two distinct domain reactions to absence (provision vs. throw), across two
services, with one of them threading the value through a transform pipeline:
that is the profile where Option stops being ceremony and starts removing a real
failure mode. A single local `?? $default` would not justify it — but that is not
what these call sites do.

## golden/ vs messy/

- **golden/** — `findByEmail(): Option<Customer>`. Call sites read as
  `->getOrElse(...)` (create-or-update) and `->map(...)->getOrThrow(...)`
  (transform-or-fail). Absence is handled exactly once, by construction.
- **messy/** — `findByEmail(): ?Customer`. Each call site re-derives the branch
  by hand with `if ($x === null)`, the transform can't chain, and nothing at the
  type level forces the handling — the null can be used by accident.
