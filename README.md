# Code Commandments

> A compiler for architecture.

**code-commandments** judges a PHP **and** Vue codebase against a set of
architectural disciplines and reports each violation — a "sin" — as a `file:line`
that points at the **skill** which teaches the fix. It's built for driving an AI
coding agent: the agent reads the skill, fixes at the source, and re-runs until
clean.

Think of it as a linter that cares about *architecture* rather than style. A
linter tells you a line is too long; code-commandments tells you *this array
should be a value object, and here's the discipline that explains why*.

## How it works

The loop is simple:

1. **Judge** — `commandments judge src` scans your code and prints every sin as a
   `file:line`, grouped by the skill that teaches the fix.
2. **Learn** — each sin points at a **skill**: a short doc describing the
   discipline, with bad-vs-good examples. You (or your AI agent) read it.
3. **Fix** — fix the sin at its source. Some sins are **auto-fixable** —
   `commandments repent` rewrites them for you (see [Auto-fixing](#auto-fixing)).
4. **Repeat** — re-run `judge` until it's clean (exit code `0`).

Under the hood there are two layers:

- **Skills** — the teaching layer, one per architectural subject. The source of
  truth for what "good" looks like. They're split by engine: backend
  (`backend/absence`, `backend/value-objects`, `backend/spatie-data`,
  `backend/laravel-idioms`, …) and frontend (`frontend/vue-components`,
  `frontend/vue-control-flow`).
- **Sin Detectors** — small finders that read the code's syntax tree. Each detector
  finds **one** kind of sin and names the skill that fixes it — it carries no fix
  logic of its own. That separation is the whole point: detectors *find*, skills
  *teach*, scribes *fix*.

> The Detectors table further down, and each skill's `SKILL.md`, are **generated**
> from the registered sins — run `composer readme` / `composer sins` to regenerate
> them; don't hand-edit.

## Install

```bash
composer require --dev jessegall/code-commandments
```

## Usage

```bash
# scan a codebase — sins grouped by the skill that fixes them
vendor/bin/commandments judge src

# scope to one skill (group) or one sin
vendor/bin/commandments judge src --skill=exceptions
vendor/bin/commandments judge src --sin=swallow-catch

# scope to what you changed: only your branch's files vs main, or just the working tree
vendor/bin/commandments judge src --branch        # new/changed on this branch vs main (--branch=BASE to override)
vendor/bin/commandments judge src --changes       # uncommitted working-tree changes only (alias: --git)

# detectors run across 8 workers by default (capped at CPU cores); --parallel=1 disables
vendor/bin/commandments judge src --parallel=4

# skip paths; list everything
vendor/bin/commandments judge src --exclude=Generated,vendor
vendor/bin/commandments judge --list
```

Exit code is non-zero when sins are found. Files marked
`@code-commandments-generated` are skipped automatically.

## Auto-fixing

Most sins are fixed by hand — the skill teaches *how*, because the right fix is
usually domain-specific. But some sins have a single, mechanical correct fix, and
for those the tool ships a **scribe**: code that rewrites the sin at its source.
The `repent` command runs them.

```bash
# preview every auto-fix as a unified diff — nothing is written
vendor/bin/commandments repent src --dry-run

# apply them
vendor/bin/commandments repent src
vendor/bin/commandments repent resources/js
```

What `repent` can currently fix, for example:

- **Spatie Data** — rename object factories to the `from<Type>` convention and
  rewrite their call sites to `::from(...)`, and keep the `@method` docblock hints
  in sync (the focused `commandments hints` command does just this part).
- **Redundant arrow-fn return types** — strip a return type PHP already infers.
- **Vue components** — extract an inline chunk into its own component (imports,
  props, and call site included), and hoist a `v-if` chain into a `<SwitchCase>`.

`repent` keeps applying scribes until nothing changes (a fixpoint), so one run
fully converges — and `--dry-run` shows exactly what an apply would produce. Run
it on the whole tree, or scope it with `--changes` / `--branch` like `judge`.

## Configuring (optional)

**You don't have to configure anything.** Every detector is enabled out of the box
with sensible defaults — install it and `judge` just works. Configuration is
purely **opt-out / opt-in**: reach for it only when a project wants to silence a
rule, tune a threshold, or add a detector of its own.

Drop a `.commandments/config.php` at your project root. It returns a closure given
a `Config` — no framework required, the CLI loads it itself:

```php
<?php

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Detectors\Backend\DataClumpDetector;
use JesseGall\CodeCommandments\Detectors\Frontend\DeepNestedDetector;
use JesseGall\CodeCommandments\Sins\Backend\NonFinalData;

return function (Config $config): void {
    $config
        // Silence a rule — by its Sin class (drops every detector for it) or a Detector class.
        ->disable(NonFinalData::class)

        // Add a detector that lives in YOUR codebase (the package can't discover it).
        ->register(\App\Commandments\NoRawSqlDetector::class)

        // Tune a threshold. The detector is auto-injected by the closure's type-hint —
        // just call its fluent setters.
        ->configure(fn (DeepNestedDetector $d) => $d->maxDepth(10))
        ->configure(fn (DataClumpDetector $d) => $d->minClasses(3));
};
```

`disable` / `register` / `configure` are the three moves. `configure` uses the
closure's **first parameter type** to find the detector and hand it in, so you tune
it by calling its own methods. Both `judge` and `repent` read this config, so they
always agree. Not every detector has knobs — the ones that do document their
setters (e.g. `DeepNestedDetector::maxDepth()`, `DataClumpDetector::minClasses()`).

## How detectors are tested

This is the part that keeps the detectors honest. Every detector is proven against
a **self-checking fixture** — a small, deliberately-imperfect example app that is
*never run*, only scanned.

You mark the exact spots where a detector *should* fire:

- **PHP** — a `#[Sinful(SomeDetector::class)]` attribute on the offending class or
  method.
- **Vue** — a `<!-- @sin SomeDetector -->` comment above the offending element.

Those markers **are** the test spec. The test harness runs every detector over the
whole fixture and fails if either:

- a marked spot is **missed** (the detector has a hole), or
- an **unmarked** spot is flagged (a false positive).

On top of that, each detector must fire on **≥3 genuinely different** examples (not
three copies of the same shape), and every detector keeps a **"righteous twin"** —
a look-alike that is *correct* and must **not** be flagged. That twin is what stops
a detector from being trigger-happy. It's a simple idea that makes adding a
detector safe: write the marker, and the fixture tells you the moment you break
something.

## Detectors

<!-- BEGIN: detectors (auto-generated — run `composer readme`) -->
_59 detectors across 16 skills._

### `backend/absence`

| Detector | What it flags |
|---|---|
| `DeNulledFinderDetector` | A `?T` finder whose result TRAVELS and is de-nulled at every stop — checked (`finder()?->…`, `=== null`, `?? default`) at two or more call sites. |
| `NullableCallbackDetector` | A nullable callback (`?callable $cb = null`) that the body null-normalises before calling — `if ($cb !== null) { $cb(…); }`, `($cb ?? fn () => …)(…)`. |
| `OptionAsNullableDetector` | An `Option` worn as a nullable — `?Option` / `Option \| null`, or `unwrapOr(null)` collapsing it straight back to a null. |

### `backend/concurrent-state`

| Detector | What it flags |
|---|---|
| `ConcurrentSubclassDetector` | A class that `extends Concurrent`. |

### `backend/documentation`

| Detector | What it flags |
|---|---|
| `ArchaeologyCommentDetector` | A comment that narrates the code's past — `// previously...`, `// changed from...`, `// now it returns...`. |
| `BloatedDocblockDetector` | A class whose docblock runs to multiple paragraphs. |
| `CeremonyDocblockDetector` | A docblock that only restates the typed signature — `@param Type $x` with no description on an already-typed parameter, plus maybe a bare `@return Type`. |

### `backend/enums-with-behaviour`

| Detector | What it flags |
|---|---|
| `ConstClassEnumDetector` | A class that is nothing but scalar constants — a closed set of values hand- rolled as `const STATUS_PENDING = 'pending'` instead of a native backed enum. |
| `EnumCaseOrChainDetector` | `$x === Status::Pending \|\| $x === Status::Paid` — a hand-rolled membership test against two-or-more cases of the same backed enum. |
| `EnumValueMatchDetector` | A `match`/`switch` over a backed enum's `->value` at a call site — the enum unwrapped to a scalar so it can be dispatched on out here. |
| `InArrayMirrorsEnumDetector` | `in_array($x, ['a', 'b', …])` whose literals ARE an existing backed enum's case values — testing membership of a set the type already seals. |
| `MatchDefaultReturnsNullDetector` | A `match` whose `default` arm returns `null`/`false`/`[]` instead of throwing. |
| `StringMatchMirrorsEnumDetector` | A `match`/`switch` whose arm conditions are string/int literals that ARE an existing backed enum's case values — dispatching on the loose strings instead of the type that already seals them. |

### `backend/exceptions`

| Detector | What it flags |
|---|---|
| `GenericExceptionDetector` | Throwing a generic SPL/base exception (`throw new \RuntimeException(...)`) instead of a named domain exception. |
| `MessageAtThrowDetector` | `throw new X("…message…")` — the failure described with a prose string at the throw site instead of a named factory carrying domain VALUES (`throw OrderNotFound::forId($id)`). |
| `SwallowCatchDetector` | A `catch` that swallows the failure into absence — an empty body, or whose only effect is `return null/false/[]`. |
| `WrappingWithoutCauseDetector` | Throwing a new exception inside a `catch` without passing the caught one on as its cause (`previous`) — the original failure and its stack trace are dropped, so the wrapped error lies about where it came from. |

### `backend/fix-at-the-source`

| Detector | What it flags |
|---|---|
| `DuplicateFunctionDetector` | Two-or-more functions/methods with an identical AST — the same code copy-pasted, down to a formatting-blind structural hash (spacing, newlines, and comments are ignored; only real code differences count). |
| `ManufacturedFakeFillDetector` | Filling an argument with a manufactured fake on absence — `name: $row['name'] ?? ''`, `(int) ($row['id'] ?? 0)`. |
| `NearDuplicateFunctionDetector` | Two-or-more functions/methods with the same SHAPE but not identical text — the same control-flow skeleton differing only in variable names or literal values (a type-2 clone). |

### `backend/guard-clauses-and-flow`

| Detector | What it flags |
|---|---|
| `DeepNestingDetector` | An `if` nested three-deep — a pyramid of conditions. |
| `IfElseLadderDetector` | An `if`/`elseif` ladder of four-plus branches — a chain of conditions doing the job of a `match`, a method on the type, or polymorphic dispatch. |
| `InlineThrowDetector` | A `?? throw` buried inside a larger expression — fed into a call or dereferenced on the same line instead of guarded at the top. |
| `LoopInvertedGuardDetector` | A loop whose entire body is wrapped in one `if` — the iteration's real work pushed a level deep behind a condition. |
| `NestedTernaryDetector` | A nested / chained ternary — `$a ? $b : ($c ? $d : $e)` — folds a branching decision into one unreadable expression where the operator precedence is a trap. |
| `RedundantElseDetector` | An `else` after an `if` branch that already exits (`return`/`throw`/`continue`/ `break`). |

### `backend/laravel-idioms`

| Detector | What it flags |
|---|---|
| `ConfigReadDetector` | Reading configuration with `config(...)` inside a class instead of injecting a typed config object. |
| `ContainerReachDetector` | Reaching into the container with `app()` / `resolve()` from a class the container itself resolves — the dependency belongs in the constructor. |
| `FacadeCallDetector` | A Laravel facade call — `Cache::get(...)`, `Log::info(...)`, `Mail::raw(...)`. |
| `MassUpdateAtCallSiteDetector` | A bare `$model->update([...])` on an Eloquent model at a call site — an anonymous array of column writes with no name and no home. |
| `ModelMutationAtCallSiteDetector` | Setting an Eloquent model's properties then calling `->save()` at a call site — `$order->status = 'paid'; $order->save();`. |
| `RawRequestInputDetector` | Raw, untyped request reads (`->input()`/`->get()`/`->query()`/`->post()`) on a request from outside the request class — use a typed accessor instead (`->string()`, `->integer()`, …). |
| `RequestAccessorRecastDetector` | Re-coercing a typed request accessor at a CALL SITE — `$request->string('id')->toString()` (or `(string) $request->string('id')`) in a handler/tool/service. |

### `backend/pass-the-object`

| Detector | What it flags |
|---|---|
| `ParamResolvedFromParamDetector` | A method that UNPACKS its target out of a container parameter — takes a container object AND a scalar key, resolves the key against the container (`request(Workflow $workflow, string $nodeId)` doing `$workflow->graph->nodeById($nodeId)`), and works on the resolved target while the container is only ever packaging. |

### `backend/role-vocabulary`

| Detector | What it flags |
|---|---|
| `NullableRegistryLookupDetector` | A class's own keyed store handing back `null` on a miss — `return $this->items[$key] ?? null`. |

### `backend/spatie-data`

| Detector | What it flags |
|---|---|
| `AllNullableDataDetector` | A Spatie Data class whose every promoted field is NULLABLE. |
| `DataMethodHintCollisionDetector` | A Spatie `Data` class with a `@method` docblock tag that names a method the class ACTUALLY declares — e.g. |
| `ManualHydrationLoopDetector` | `<Data>::from(...)` called per item of a collection — inside a `foreach`/`for`/ `while` loop, or as an `array_map` callback (`array_map(X::from(...), $rows)`, `array_map(fn ($r) => X::from($r), $rows)`). |
| `NewDataObjectDetector` | Constructing a RICH Spatie `Data` object with `new` instead of `::from()` — the raw `new` skips the work `::from()` does: a cast, a name map, a nested-Data hydration, or a magic `fromX()` factory. |
| `NonFinalDataDetector` | A Spatie `Data` class that is not declared `final`. |

### `backend/tell-dont-ask`

| Detector | What it flags |
|---|---|
| `FeatureEnvyDetector` | Exiled behaviour (feature envy) — a method that reaches THROUGH one other owned object's structure, iterating its collection, to do work that belongs ON that object (`$node->edges()`, not `EdgeDetector::detect($node)`). |
| `KeyedLookupEnvyDetector` | Feature envy through an indirect lookup — a method that uses an owned object's identity as a KEY to fetch data about it through a collaborator, then reads a fact back (`$this->registry->get($node->key)->reservedOutputNames`). |

### `backend/type-honesty`

| Detector | What it flags |
|---|---|
| `MaskedInvariantDetector` | `$this->scratch?->call() ?? false` — defaulting a reach into the object's own TRANSIENT nullable state. |
| `ScratchStateRestoreDetector` | A method that SAVES one of its own properties to a local and RESTORES it afterwards — `$prev = $this->scope; … $this->scope = $prev;`. |

### `backend/value-objects`

| Detector | What it flags |
|---|---|
| `ArrayBagDetector` | An `array` parameter read by a string-literal key (`$bag['total']`) — a structured bag that should be a typed value object. |
| `ArrayReturnBagDetector` | Returning a multi-field, string-keyed array literal — a structured bag that should be a typed value object. |
| `DataClumpDetector` | The same three-or-more value parameters (`string $shopId, string $userId, string $channelId`) threaded through two-or-more signatures in different classes. |
| `PositionalTupleReturnDetector` | Returning a positional TUPLE — `return [$node, $key, $inputs, $outputs]` (also from a closure / arrow fn) — bundles several independent values as a keyless list the caller must destructure by position. |
| `RawDecodedArrayReturnDetector` | Returning a freshly-decoded payload straight out of a boundary — the raw `array` from `json_decode(...)` crossing back into the app untyped. |

### `frontend/vue-components`

| Detector | What it flags |
|---|---|
| `CompoundInlineComponentDetector` | A compound UI primitive assembled INLINE — a component (`<Dialog>`, `<Card>`, `<Sheet>`, `<Tabs>`) whose family parts (`DialogContent`/`DialogTitle`/`DialogFooter`) are filled with a substantial body right here in the parent template, instead of living in its own component. |
| `DeepDataReachDetector` | A CLUSTER of deep data reaches that share one nested object — an element binding or interpolating `order.customer.name`, `order.customer.email`, … from several places in a sizeable template. |
| `DeepNestedDetector` | A template nested far too deep — an element {@see $maxDepth}+ levels in that still has {@see $maxRemaining}+ levels of markup beneath it. |
| `DuplicateElementDetector` | Two-or-more identical blocks of template markup — the same tags, attributes and children, copy-pasted (the comparison is by STRUCTURE, blind to formatting, whitespace and line numbers). |
| `PropDrillingDetector` | Prop DRILLING — a prop threaded through a component that doesn't use it, on its way to a child that doesn't either. |
| `PropMutationDetector` | A component WRITES one of its own props — `v-model="open"` bound to a prop, or an event handler assigning to it (`@click="confirmingClose = true"`). |

### `frontend/vue-control-flow`

| Detector | What it flags |
|---|---|
| `ControlFlowOnElementDetector` | A control-flow directive — `v-if` / `v-else-if` / `v-else` / `v-for` — sitting on a real element or component instead of a `<template>`. |
| `IndexAsKeyDetector` | A `v-for` whose `:key` is the loop INDEX — `v-for="(item, index) in items" :key="index"`. |
| `LoopWithConditionDetector` | A `v-for` and a `v-if`/`v-else-if` on the SAME element. |
| `SwitchCaseDetector` | A `v-if` / `v-else-if` chain whose every branch tests the SAME value against a different case — a switch wearing conditionals. |

<!-- END: detectors -->

## Developing detectors

A detector is a few lines of fluent AST query. Before writing one, load the
project skills (via Claude Code's Skill tool): **`writing-detectors`**,
**`detector-engine`**, **`detector-fixtures`**. The cardinal rule is AST/semantic
detection over name matching. See [`CLAUDE.md`](CLAUDE.md); each sin is its own class
under `src/Sins/`, and a detector references one.

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
