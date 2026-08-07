---
name: detector-engine
description: The v4 fluent AST engine a Sin Detector queries — Codebase selectors, Query filters, the null-object AstNode/NodeMatch, the call graph (index/callersOf/ReceiverResolver), the variable trace, TypeName, and WHERE a new helper belongs (the layering rule). Read this when writing or changing a detector, or when you reach for a predicate the engine doesn't have yet.
---

# The detector engine — query the AST fluently

The engine lives in `src/Ast/`. A detector never touches nikic/php-parser directly;
it composes a fluent query and reads the result. Everything reads as English.

```php
$codebase
    ->whereMethod('input', 'get')              // a SELECTOR opens a Query
    ->isUsedOn('Illuminate\\Http\\Request')    // FILTERS narrow it
    ->reject(fn (AstNode $n) => $n->isInEnum())
    ->get();                                    // a TERMINAL returns list<NodeMatch>
```

## The three layers (and where a new helper goes)

When a detector needs a predicate the engine lacks, **add it at the right layer** —
never inline AST poking in the detector, never a name/suffix list.

1. **`AstNode`** — language-level, codebase-agnostic predicates over ONE node and
   its parents. `isThrow()`, `newClassName()`, `isInEnum()`, `coalesceRight()`,
   `isReturnedValue()`, `isParameterDefault()`, `hasNestedArrayValue()`. The
   null-object rule: the fluent navigators (`parent()`, `coalesceRight()`,
   `coalesceLeft()`) never return null — each returns another `AstNode` whose
   predicates are all false — so patterns read `$n->coalesceRight()->isThrow()` with
   no `?->`. (Raw accessors like `enclosingClass(): ?ClassLike` /
   `enclosingClassName(): ?string` CAN be null — they're not fluent navigators.)
2. **`Codebase`** — whole-program queries that need the class graph. Selectors
   (`whereMethod`, `whereNew`, `whereClass`, `whereClassExtending`,
   `whereMethodDeclaration`, `whereStaticCall`, `whereFunction`, `whereParamType`,
   `whereComment`, `whereAttribute`, and bare `where(\Closure)`), plus `extends()`
   and the lazy `index()` (call graph).
3. **`src/Ast/Support/`** — a framework/cross-cutting concept used by **≥2**
   detectors (e.g. `ReceiverResolver`, `ChainResolver`). Rule-specific composition and
   domain constants stay **in the detector**.

> Smell test: if you're about to write `const SOMETHING_BASES = [...]` or
> `str_ends_with($name, 'Data')`, stop — the AST/type already answers it. A name
> check is a smell to justify, not a default. See `prefer_ast_over_name_checks`.

## Query — `where` / `reject`, one check per line

`where(fn (AstNode $n): bool => …)` keeps matches that pass; `reject(...)` drops
them. **One check per line** — split a compound predicate into several `where`s.
Other filters: `isUsedOn($fqcn)`, `withinClass`/`notWithinClass`, `inProximityOf`.
Terminals: `get(): list<NodeMatch>`, `locations()`, `count()`, `first()`.
A `where` closure is actually handed a `NodeMatch` at runtime, so to use
NodeMatch-only methods guard with `$n instanceof NodeMatch && $n->trace()`.

## NodeMatch — a finding that knows where it is

`NodeMatch extends AstNode` and adds `file`, `line()`, `location()` (`path:line`),
`near()`, and `trace()` (plus `span()`, `resultIsDeNulled()`,
`receiverMutatedNearby()`). `scope()` (`Class::method`) is inherited from `AstNode`.
A detector returns these.

## The call graph — `Codebase::index()`

`index()->callersOf($fqcn, $method)` returns every resolved call site of a method
(receiver typed via `ReceiverResolver`: `$this`, a typed param, `$this->typedProp`).
Used for measure-and-suppress detectors — e.g. flag a `?T` finder only when ≥2
callers de-null its result (blast radius).

## The variable trace — follow a value's journey

`$variableMatch->trace()` returns `list<Interaction>`, one per occurrence of that
variable in its function, each an `InteractionKind` (Assigned, Argument,
MethodCall, PropertyFetch, PropertyWrite, NullChecked, Coalesced, Nullsafe,
Returned, Read). `Interaction::deNulls()` / `isWrite()` read the journey. This is
the dataflow substrate — `resultIsDeNulled()` (blast radius) and
`receiverMutatedNearby()` (model mutation) both use it; reach for it before
hand-rolling a `NodeFinder` scan.

## TypeName

`TypeName::class($type)` / `nullableClass` / `isNullable` / `isNullableArray` read a
class FQCN (or kind) out of a type declaration — builtins yield null. Names are
resolved at parse time, so everything is fully-qualified.

## Related

- [[writing-detectors]] — how to author a detector using this engine.
- [[detector-fixtures]] — the self-checking fixture that proves it.
