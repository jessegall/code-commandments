# null-right-cache-miss

## Verdict: a nullable `?ExchangeRate` is RIGHT here — wrapping the miss in an `Option` is over-engineering.

`RateMemo::lookup()` is a thin read off a cache where `null` is already the
idiomatic, well-understood "miss" signal (`Cache::get()` returns `?T`). The
question is never "is Option nicer in the abstract" — it is **how do the call
sites consume the result**. Here they consume it in exactly the two ways that
make a nullable correct and an Option pointless.

## What the call sites actually do

- **`RateProvider::rateFor()` (golden)** — one clean
  `$this->memo->lookup($pair) ?? $this->refresh($pair)`. Absence means "recompute
  on miss," the single most natural job for `??`. There is no transform to chain,
  no value threaded onward, no second consumer juggling the absence.

- **`PriceTag::freshnessBadge()` (golden)** — a single `?->asOf` that collapses
  absence to `"stale"`. Again: one local branch, no chaining, absence is just
  "not cached," not a domain event.

Two callers, each doing a single `?? default` / `?->`. That is the textbook
profile where null wins.

## Why the messy version is wrong

The messy `lookup()` returns `Option::fromNullable(...)` — but the cache value
was *already* `?ExchangeRate`, so the Option is built only to be torn straight
back open at every call site:

- `rateFor()` does `->isSome()` + `->getOrThrow()` — an `if/return` that is
  strictly longer than the `?? $this->refresh()` it replaced, with no behaviour
  gained.
- `freshnessBadge()` does `->getOrElse(null)` — round-tripping
  value → Option → value to land back on the exact nullable it started with.

Every caller unwraps the Option in the same line it receives it. The box never
travels, never gets `map`/`flatMap`-ed, never forces a caller to handle absence
they would otherwise have missed (the `??` and `?->` already handle it). It is
pure ceremony: more types, more imports, more characters, identical semantics.

## When this verdict would flip

If a caller threaded the rate through a transform pipeline
(`->map(convert)->map(round)->getOrElse(...)`), or three+ callers each had to
re-coalesce the same propagating nullable, or absence were a real domain event
worth forcing every consumer to confront — then Option would earn its place.
None of that is true here, so it does not.
