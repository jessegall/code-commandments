# null-right-framework-idiom

## Verdict: a nullable `?Team` is RIGHT here. An `Option<Team>` is over-engineering.

`User::currentTeam()` mirrors an idiomatic framework nullable relation (cf.
Jetstream's `User::currentTeam(): ?Team`), where `null` means nothing more than
"no team selected yet" — not a domain event worth ceremony.

## Why the call sites drive this

There are only two thin call sites, and **both immediately collapse the value
straight back to value-or-null**:

- `TopBarComposer::teamLabel()` does a single clean `$user->currentTeam()?->name ?? '—'`.
  In `messy/` the same logic becomes `->map(...)->getOrElse('—')` — identical
  outcome, more moving parts, and now fighting the `?->name ?? '—'` idiom the
  rest of the framework uses.
- `TeamGate::hasTeam()` is a plain `!== null`. In `messy/` it is `->isSome()` —
  a renamed null check that buys zero additional safety.

No caller **threads** the team through a chain of transforms, no caller
re-coalesces a propagating nullable over and over, and absence is not juggled by
several independent consumers. Each site unwraps the Option in the very next
expression. That is exactly the shape where Option earns nothing: it adds a
construct/destruct round-trip with no call-site benefit while breaking the
framework's nullable convention.

Option would earn its place if absence were threaded through `map`/`flatMap`
across multiple steps, or if a nullable kept propagating and getting
re-defaulted. None of that happens here — so `?Team` is the honest design.

- `golden/` — nullable `?Team`, clean `?->`/`?? '—'`/`!== null` call sites.
- `messy/` — `Option<Team>` built then immediately unwrapped at every site.
