# Code Commandments

> A compiler for architecture.

**code-commandments** judges a PHP codebase against a set of architectural
disciplines and reports each violation — a "sin" — as a `file:line` that points at
the **skill** which teaches the fix. It's built for driving an AI coding agent:
the agent reads the skill, fixes at the source, and re-runs until clean.

It pairs two layers:

- **Skills** — the teaching layer, one per architectural subject (`absence`,
  `value-objects`, `spatie-data`, `exceptions`, `enums-with-behaviour`,
  `laravel-idioms`, `role-vocabulary`, `concurrent-state`, `documentation`,
  `fix-at-the-source`). The source of truth for what good looks like.
- **Sin Detectors** — thin finders over a fluent AST engine. Each finds **one**
  sin and names the skill that fixes it; it carries no fix logic.

Every detector is proven against a self-checking fixture where `#[Sinful]`
attributes are the test spec, and must fire on ≥3 genuinely-different scenarios
while leaving a righteous look-alike untouched.

## Install

```bash
composer require --dev jessegall/code-commandments
```

## Usage

```bash
# scan a codebase — sins grouped by the skill that fixes them
vendor/bin/commandments judge src

# scope to one skill (group) or one detector
vendor/bin/commandments judge src --skill=exceptions
vendor/bin/commandments judge src --detector=SwallowCatch

# skip paths; list everything
vendor/bin/commandments judge src --exclude=Generated,vendor
vendor/bin/commandments judge --list
```

Exit code is non-zero when sins are found. Files marked
`@code-commandments-generated` are skipped automatically.

## Detectors

<!-- BEGIN: detectors (auto-generated — run `composer readme`) -->
_33 detectors across 11 skills._

### `absence`

| Detector | What it flags |
|---|---|
| `DeNulledFinderDetector` | A `?T` finder whose result TRAVELS and is de-nulled at every stop — checked (`finder()?->…`, `=== null`, `?? default`) at two or more call sites. |
| `NullableCollectionReturnDetector` | A method declared to return `?array` / `array \| null` — a collection modelled as "the list, or null", forcing every caller to guard before iterating. |
| `OptionAsNullableDetector` | An `Option` worn as a nullable — `?Option` / `Option \| null`, or `unwrapOr(null)` collapsing it straight back to a null. |

### `concurrent-state`

| Detector | What it flags |
|---|---|
| `ConcurrentSubclassDetector` | A class that `extends Concurrent`. |

### `documentation`

| Detector | What it flags |
|---|---|
| `ArchaeologyCommentDetector` | A comment that narrates the code's past — `// previously...`, `// changed from...`, `// now it returns...`. |
| `BloatedDocblockDetector` | A class whose docblock runs to multiple paragraphs. |

### `enums-with-behaviour`

| Detector | What it flags |
|---|---|
| `ConstClassEnumDetector` | A class that is nothing but scalar constants — a closed set of values hand- rolled as `const STATUS_PENDING = 'pending'` instead of a native backed enum. |
| `EnumValueMatchDetector` | A `match`/`switch` over a backed enum's `->value` at a call site — the enum unwrapped to a scalar so it can be dispatched on out here. |
| `MatchDefaultReturnsNullDetector` | A `match` whose `default` arm returns `null`/`false`/`[]` instead of throwing. |
| `StringMatchMirrorsEnumDetector` | A `match`/`switch` whose arm conditions are string/int literals that ARE an existing backed enum's case values — dispatching on the loose strings instead of the type that already seals them. |

### `exceptions`

| Detector | What it flags |
|---|---|
| `GenericExceptionDetector` | Throwing a generic SPL/base exception (`throw new \RuntimeException(...)`) instead of a named domain exception. |
| `MessageAtThrowDetector` | `throw new X("…message…")` — the failure described with a prose string at the throw site instead of a named factory carrying domain VALUES (`throw OrderNotFound::forId($id)`). |
| `SwallowCatchDetector` | A `catch` that swallows the failure into absence — an empty body, or whose only effect is `return null/false/[]`. |
| `WrappingWithoutCauseDetector` | Throwing a new exception inside a `catch` without passing the caught one on as its cause (`previous`) — the original failure and its stack trace are dropped, so the wrapped error lies about where it came from. |

### `fix-at-the-source`

| Detector | What it flags |
|---|---|
| `ManufacturedFakeFillDetector` | Filling an argument with a manufactured fake on absence — `name: $row['name'] ?? ''`, `(int) ($row['id'] ?? 0)`. |

### `guard-clauses-and-flow`

| Detector | What it flags |
|---|---|
| `DeepNestingDetector` | An `if` nested three-deep — a pyramid of conditions. |
| `IfElseLadderDetector` | An `if`/`elseif` ladder of four-plus branches — a chain of conditions doing the job of a `match`, a method on the type, or polymorphic dispatch. |
| `InlineThrowDetector` | A `?? throw` buried inside a larger expression — fed into a call or dereferenced on the same line instead of guarded at the top. |
| `LoopInvertedGuardDetector` | A loop whose entire body is wrapped in one `if` — the iteration's real work pushed a level deep behind a condition. |

### `laravel-idioms`

| Detector | What it flags |
|---|---|
| `ConfigReadDetector` | Reading configuration with `config(...)` inside a class instead of injecting a typed config object. |
| `ContainerReachDetector` | Reaching into the container with `app()` / `resolve()` from a class the container itself resolves — the dependency belongs in the constructor. |
| `FacadeCallDetector` | A Laravel facade call — `Cache::get(...)`, `Log::info(...)`, `Mail::raw(...)`. |
| `MassUpdateAtCallSiteDetector` | A bare `$model->update([...])` on an Eloquent model at a call site — an anonymous array of column writes with no name and no home. |
| `ModelMutationAtCallSiteDetector` | Setting an Eloquent model's properties then calling `->save()` at a call site — `$order->status = 'paid'; $order->save();`. |
| `RawRequestInputDetector` | Raw, untyped request reads (`->input()`/`->get()`/`->query()`) on a Request from outside the request class. |

### `role-vocabulary`

| Detector | What it flags |
|---|---|
| `NullableRegistryLookupDetector` | A class's own keyed store handing back `null` on a miss — `return $this->items[$key] ?? null`. |

### `spatie-data`

| Detector | What it flags |
|---|---|
| `AllNullableDataDetector` | A Spatie Data class whose every promoted field is optional — nullable or defaulted. |
| `ManualHydrationLoopDetector` | `<Data>::from(...)` called inside a loop — hydrating a collection one item at a time. |
| `NewDataObjectDetector` | Constructing a Spatie `Data` object with `new` instead of `::from()` — the raw `new` skips name mapping, casts, and validation. |
| `NonFinalDataDetector` | A Spatie `Data` class that is not declared `final`. |

### `value-objects`

| Detector | What it flags |
|---|---|
| `ArrayBagDetector` | An `array` parameter read by a string-literal key (`$bag['total']`) — a structured bag that should be a typed value object. |
| `ArrayReturnBagDetector` | Returning a multi-field, string-keyed array literal — a structured bag that should be a typed value object. |
| `RawDecodedArrayReturnDetector` | Returning a freshly-decoded payload straight out of a boundary — the raw `array` from `json_decode(...)` crossing back into the app untyped. |

<!-- END: detectors -->

## Developing detectors

A detector is a few lines of fluent AST query. Before writing one, load the
project skills (via Claude Code's Skill tool): **`writing-detectors`**,
**`detector-engine`**, **`detector-fixtures`**. The cardinal rule is AST/semantic
detection over name matching. See [`CLAUDE.md`](CLAUDE.md) and the roadmap in
[`SINS.md`](SINS.md).

```php
final class FacadeCallDetector implements Detector
{
    public function skill(): string { return 'laravel-idioms'; }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStaticCall()
            ->where(fn (AstNode $n): bool => str_starts_with($n->staticCallClass() ?? '', self::FACADE_NS))
            ->get();
    }
}
```

## License

MIT.
