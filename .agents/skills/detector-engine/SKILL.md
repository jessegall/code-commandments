---
name: detector-engine
description: The Go engine a Sin Detector queries — engine.Codebase selectors, engine.Query filters, engine.Match, a language's decorator reached with engine.As, the call graph (php.IndexOf → CallersOf), the variable trace (php.Trace), and WHERE a new helper belongs (the layering rule). Read this when writing or changing a detector, or when you reach for a predicate the engine doesn't have yet.
---

# The detector engine — query the tree fluently

Every language reaches the engine the same way: its bridge parses it with the language's own parser and writes
the generic tree (`contract/CONTRACT.md`), and `engine.Load` holds every stream as one `engine.Codebase`. A
detector never touches a parser; it composes a query and reads the result.

```go
php.In(codebase).                               // a language narrows the codebase
	WhereKind("Expr_MethodCall").               // a SELECTOR opens a Query
	Where(engine.As(laravel.Node.IsFacadeCall)). // FILTERS narrow it, one check per line
	Reject(engine.As(php.Node.IsInEnum)).
	Get()                                       // a TERMINAL returns []engine.Match
```

## The layers (and where a new helper goes)

When a detector needs a predicate the engine lacks, **add it at the right layer** — never inline tree poking in
the detector, never a name or suffix list.

1. **`engine.Match`** (`engine/match.go`) — what every language's node answers: navigation (`Parent`,
   `Children`, `Child`, `ChildrenIn`, `Descendants`, `Closest`, `Root`, `EnclosingType`, `EnclosingFunction`),
   reads (`Kind`, `Name`, `Text`, `Written`, `Is`, `Refers`, `Resolves`, `HasFlag`, `HasModifier`,
   `Comments`, `IsDocumented`, `IsWithinLoop`, `SameSyntax`) and location (`Line`, `Location`, `Scope`,
   `Span`). A navigator never answers nil: a missing parent is a match whose predicates are all false, so a
   chain reads with no checks between.
2. **A language's decorator** — `php.Node`, `typescript`, `vue`, `python`, `csharp`: the language's own
   knowledge about ONE node, stated once (`php.Node` has ~200 predicates — skim before adding one). A
   detector reaches one with `engine.As(php.Node.IsField)`, which the query hands the decorated node.
3. **An analysis** — a whole-program concept used by **≥2** detectors, memoised per codebase and the single
   home of its concept: `php.IndexOf` (call graph), `php.ExpressionType`/`ReceiverTypeOf` (types),
   `php.ValueFlowOf`, `php.Trace`, `engine.Recurring`/`NearCopies`, `engine.DivergentTwins`, `engine.Layers`.
   A third-party package's knowledge has its own package under the engine (`engine/php/laravel`,
   `engine/php/spatie`, …). Rule-specific composition and constants stay **in the detector**.
4. **The bridge** — when the tree does not carry a fact the detector needs (a resolved type, an outside
   symbol), the bridge writes it and the contract documents it; the engine never guesses it back from text.

> Smell test: if you're about to write a list of base-class names or `strings.HasSuffix(name, "Data")`, stop
> — the tree or the resolved type already answers it. A name check is a smell to justify, not a default.

## Query — `Where` / `Reject`, one check per line

`Where(check)` keeps matches that pass; `Reject(check)` drops them. **One check per line** — split a compound
predicate into several. Terminals: `Get() []engine.Match`, `Locations()`, `Count()`, `First()`.

## The call graph — `php.IndexOf`

`php.IndexOf(codebase).CallersOf(fqcn, method)` answers every resolved call site of a method (the receiver
typed through `$this`, a typed parameter, a typed property). Used for measure-and-suppress detectors — e.g.
flag a `?T` finder only when ≥2 callers de-null its result.

## The variable trace — follow a value's journey

`php.Trace(variable)` answers one `Interaction` per occurrence of the variable in its function, each of a kind
(assigned, an argument, a method call on it, a property read or write, null-checked, coalesced, null-safe,
returned, read). `Interaction.IsWrite()` and friends read the journey. Reach for it before hand-rolling a walk
over a function's nodes.

## Testing a helper

Each language has a source builder that parses through the real bridge — `frontendtest.FromSource`,
`pythontest.FromSource`, `csharptest.FromSource` — and PHP answers are held to the frozen shop
(`engine/php/shop`). Run Go only through `scripts/dev`, scoped to the package you touched.

## Related

- [[writing-detectors]] — how to author a detector using this engine.
- [[detector-fixtures]] — the self-checking fixture that proves it.
