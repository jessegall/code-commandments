# Code Commandments

> An architecture linter for PHP & Vue — built to drive AI coding agents.

**code-commandments** judges a PHP **and** Vue codebase against a set of
architectural disciplines and reports each violation — a "sin" — as a `file:line`
that points at the **skill** which teaches the fix.

It's built **first for AI coding agents**: point your agent at a codebase, and it
reads the skill each sin names, fixes at the source, and re-runs until clean. You
can drive it by hand too — but the whole design is agent-first, so the output is a
worklist and a curriculum, not a wall of warnings for a human to triage.

Think of it as a linter that cares about *architecture* rather than style. A
linter tells you a line is too long; code-commandments tells you *this array
should be a value object, and here's the discipline that explains why*.

## Contents

- [How it works](#how-it-works)
- [Install](#install)
- [Usage](#usage)
- [Configuration](#configuration)
- [How detectors are tested](#how-detectors-are-tested)
- [Skills](#skills)
- [Sins & detectors](#sins--detectors)
- [Auto-fixing](#auto-fixing)
- [Scaffolding](#scaffolding)
- [Developing detectors](#developing-detectors)
- [License](#license)

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
  (`backend/absence`, `backend/value-objects`, `backend/exceptions`,
  `backend/guard-clauses-and-flow`, …) and frontend (`frontend/vue-components`,
  `frontend/vue-control-flow`).
- **Sin Detectors** — small finders that read the code's syntax tree. Each detector
  finds **one** kind of sin and names the skill that fixes it — it carries no fix
  logic of its own. That separation is the whole point: detectors *find*, skills
  *teach*, scribes *fix*.

> The tables further down (sins, detectors, auto-fixes), and each skill's
> `SKILL.md`, are **generated** from the registered sins — run `composer readme` /
> `composer sins` to regenerate them; don't hand-edit.

**You don't need the packages a rule is about.** Detectors match on real *types* —
a Spatie `Data` subclass, a `jessegall/concurrent` proxy, an Eloquent model — so a
rule for a package your project doesn't use simply never fires: with no such class
in your code, there's nothing for it to match. Drop code-commandments into any
PHP/Vue project and the detectors (and skills) for tools you don't have just sit
dormant — you don't install, implement, or configure anything to make them
harmless. You only ever see the sins that actually apply to your code.

## Install

```bash
composer require --dev jessegall/code-commandments
vendor/bin/commandments install
```

`install` wires the project up (idempotent): a composer hook that re-syncs the
skills on every `composer update`; a `UserPromptSubmit` hook that reminds your agent
of the rule above all — *a finding is a symptom, so trace it to where the bad value
is born and fix it there, never at the call site*; the `.gitignore` entries; and a
commented `.commandments/config.php` scaffold.

## Usage

```bash
# scan — sins grouped by the skill that fixes them. No path needed: with none,
# judge uses the source roots in .commandments/backend.canon (written on first run).
vendor/bin/commandments judge
vendor/bin/commandments judge src                  # ...or point it at a path

# scope to one skill (group) or one sin
vendor/bin/commandments judge src --skill=exceptions
vendor/bin/commandments judge src --sin=swallow-catch

# scope to what you changed
vendor/bin/commandments judge src --branch         # branch vs main (--branch=BASE to override)
vendor/bin/commandments judge src --changes        # uncommitted working-tree changes

# detectors run across 8 workers by default (capped at CPU cores); --parallel=1 disables
vendor/bin/commandments judge src --parallel=4

# skip paths (comma-separated fragments); list everything
vendor/bin/commandments judge src --exclude=Generated,Legacy
vendor/bin/commandments judge --list
```

Exit code is non-zero when sins are found.

## Configuration

**You don't have to configure anything.** Every detector is enabled out of the box
with sensible defaults — install it and `judge` just works. Configuration is
purely **opt-out / opt-in**: reach for it only when a project wants to silence a
rule, tune a threshold, or add a detector of its own.

And to say it again: even though code-commandments ships skills and detectors for
specific packages (Spatie Data, `jessegall/concurrent`, …), you **don't** need any
of them installed. Those rules only fire when the matching *type* is present in
your code — so on a project that doesn't use them, they never trigger and there's
nothing to disable. You configure a rule to change it, never to make an
irrelevant one harmless.

A commented `.commandments/config.php` is scaffolded for you on install. Edit it —
it returns a closure given a `Config`; no framework required, the CLI loads the file
itself:

```php
<?php

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Detectors\Backend\DataClumpDetector;
use JesseGall\CodeCommandments\Detectors\Backend\Laravel\FacadeCallDetector;
use JesseGall\CodeCommandments\Detectors\Frontend\DeepNestedDetector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\NonFinalData;
use JesseGall\CodeCommandments\Skills\Backend\ValueObjects;

return function (Config $config): void {
    $config
        // Silence a rule by its Sin class (drops the detector that finds it).
        ->disable(NonFinalData::class)

        // ...or by a specific Detector class.
        ->disable(FacadeCallDetector::class)

        // ...or by a whole Skill class (every detector that discipline teaches).
        ->disable(ValueObjects::class)

        // Add a detector that lives in YOUR codebase.
        ->register(\App\Commandments\NoRawSqlDetector::class)

        // Tune thresholds — name the detector, then set it. Its setters chain, so
        // you can tune several knobs in one closure.
        ->configure(fn (DeepNestedDetector $d) => $d->maxDepth(10)->maxRemaining(2))
        ->configure(fn (DataClumpDetector $d) => $d->minClasses(3));
};
```

`disable` / `register` / `configure` are the three moves. `configure` uses the
closure's **first parameter type** to find the detector and hand it in, so you tune
it by calling its own methods.

## How detectors are tested

This is the part that keeps the detectors honest. Every detector is proven against
a **self-checking fixture** — a small, deliberately-imperfect example app that is
*never run*, only scanned.

You mark the exact spots where a detector *should* fire — by naming the **sin** (the
stable, first-class concept). Naming the detector class works too, but the sin is the
one to prefer:

- in PHP, a `#[Sinful(...)]` attribute;
- in Vue, a `<!-- @sin ... -->` comment.

```php
// tests/Fixtures/shop/app/Orders/RefundService.php
use JesseGall\CodeCommandments\Sins\Backend\SwallowCatch;
use JesseGall\CodeCommandments\Testing\Sinful;

final class RefundService
{
    // the marker IS the assertion: the SwallowCatch detector must flag this method.
    // if it doesn't fire here, the fixture test fails.
    #[Sinful(SwallowCatch::class)]
    public function refund(Order $order): void
    {
        try {
            $this->gateway->refund($order->id);
        } catch (\Throwable) {
            // swallowed into silence — the sin
        }
    }
}
```

```vue
<!-- tests/Fixtures/shop-frontend/components/UserBadge.vue -->
<template>
  <!-- the marker IS the assertion: the next element must be flagged -->
  <!-- @sin ControlFlowOnElement -->
  <div v-if="user">{{ user.name }}</div>

  <!-- the good-code example; if this gets flagged, the test fails -->
  <!-- @righteous ControlFlowOnElement -->
  <template v-if="user">
    <div>{{ user.name }}</div>
  </template>
</template>
```

Those markers **are** the test spec. The test harness runs every detector over the
whole fixture and fails if either:

- a marked spot is **missed** (the detector has a hole), or
- an **unmarked** spot is flagged (a false positive).

On top of that, each detector must fire on **≥3 genuinely different** examples (not
three copies of the same shape).

And the nice part: **any unmarked code is already "righteous."** The whole rest of
the fixture is the false-positive guard — flagging an *unmarked* spot fails the run —
so you don't have to mark "good" code at all. You just need **one** `#[Righteous]` /
`<!-- @righteous -->` per detector: it sources the concrete *good-code example* for
the generated skill docs (the bad→good block). That one is required; add more if
they're illustrative.

### Testing your own detectors

The same harness proves the detectors *you* write. A custom detector declares where
its own marked fixture files live by implementing `HasFixture` — put them anywhere in
your repo, one directory per detector (several detectors may share one):

```php
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Testing\HasFixture;

final class NoRawSqlDetector implements Detector, HasFixture
{
    // the directory of .php files carrying #[Sinful(NoRawSql::class)] / #[Righteous] markers
    public function fixturePath(): string
    {
        return __DIR__ . '/fixtures';
    }

    // sin() + find() as usual …
}
```

Then a one-class test hands your detectors to a `DeclaredFixture` and extends the
shipped `FixtureTestCase` — you get the exact checks the package runs on itself (every
marked sin flagged, nothing unmarked flagged, ≥3 diverse scenarios):

```php
use JesseGall\CodeCommandments\Testing\DeclaredFixture;
use JesseGall\CodeCommandments\Testing\Fixture;
use JesseGall\CodeCommandments\Testing\FixtureTestCase;

final class MyDetectorsTest extends FixtureTestCase
{
    protected function fixture(): Fixture
    {
        return new DeclaredFixture([
            new NoRawSqlDetector(),
            // a Frontend\Detector — .vue fixtures with <!-- @sin --> markers
            new NoDatePickerDetector(),
        ]);
    }
}
```

Frontend detectors work identically — implement `HasFixture`, point it at a directory
of `.vue` files with `<!-- @sin -->` markers; `DeclaredFixture` routes each detector to
its engine automatically.

## Skills

The teaching layer — one discipline each, the doc an agent reads to fix a sin. Every
`SKILL.md` is generated from its class (`composer sins`).

<!-- BEGIN: skills (auto-generated — run `composer readme`) -->
_16 skills._

### Backend

| Skill | What it teaches |
|---|---|
| `Absence` | modelling a value that might be missing (`?T`, `Option`, `null`, empty, Null Object, throw). |
| `ConcurrentState` | state shared across requests/workers (`::for($id): Concurrent<self>`). |
| `Documentation` | concise, present-tense docs; rare inline comments; never narrate the past. |
| `EnumsWithBehaviour` | a closed set of values: seal it as a native backed enum, put the per-case logic on the enum (not a `match` at every call site). |
| `Exceptions` | throwing or catching: named `::for()` factory exceptions, never swallow a failure. |
| `FixAtTheSource` | the root-cause-first move: trace a value to where it's born, never patch the symptom. Governs how every change is made. |
| `GuardClausesAndFlow` | validate preconditions at the TOP (early return/throw), flat body, happy path last; never bury a check inline. |
| `LaravelIdioms` | typed request/bag access (never raw `->input()`/`->get()`), required constructor DI (never `app()`/facade), Eloquent scopes + intention-revealing model mutation methods. |
| `PassTheObject` | demand the resolved type you need, not an id plus its container: a method that takes `(Workflow $workflow, string $nodeId)` then unpacks `$workflow->graph->nodeById($nodeId)` should take the node — the caller resolves once and passes the object (and owns the not-found failure). |
| `RoleVocabulary` | a keyed store / membership set / first-match dispatcher: name it `*Registry`/`*Set`/`*Resolver`, extend the base, honour the contract. |
| `SpatieData` | how to write and construct Spatie `Data` classes — `::from()` not `new`, total types, sealed and readonly. |
| `TellDontAsk` | behaviour belongs with its data (feature envy): don't exile a loop over one object's collection into a separate class — move it onto the object (`$node->edges()`, not `EdgeDetector::detect($node)`). A Strategy over flat scalar fields is the exception. |
| `TypeHonesty` | a type must not lie: don't fake optionality — a `?T` the design always has set, then defended with `?->`/`?? <fake>` or stashed as save/restore scratch state. Make the type certain (pass it, hold it non-nullable, a per-call value object). The complement of `absence`. |
| `ValueObjects` | give related data a type: no loose `array<string,mixed>` bags, no data clumps, no primitive obsession. (Decide the type; then `spatie-data` is how to write it.) |

### Frontend

| Skill | What it teaches |
|---|---|
| `VueComponents` | extract a component when template markup REPEATS, or when an element reaches DEEP into nested data — pass it the mid-object as a prop. |
| `VueControlFlow` | dispatch on a value with `<SwitchCase :value>` (a slot per case), never a `v-if`/`v-else-if` chain re-testing the same subject. |
<!-- END: skills -->

## Sins & detectors

Every sin (the `--sin=` key) and what it flags, grouped by the skill that teaches
the fix and split by engine. Each sin has one detector that finds it, named
`<Sin>Detector` (e.g. `SwallowCatch` → `SwallowCatchDetector`).

<!-- BEGIN: detectors (auto-generated — run `composer readme`) -->
_59 sins across 16 skills._

### Backend

#### `backend/absence`

| Sin | What it flags |
|---|---|
| `DeNulledFinder` | A `?T` finder whose result TRAVELS and is de-nulled at every stop — checked (`finder()?->…`, `=== null`, `?? default`) at two or more call sites. |
| `NullableCallback` | A nullable callback (`?callable $cb = null`) that the body null-normalises before calling — `if ($cb !== null) { $cb(…); }`, `($cb ?? fn () => …)(…)`. |
| `OptionAsNullable` | An `Option` worn as a nullable — `?Option` / `Option \| null`, or `unwrapOr(null)` collapsing it straight back to a null. |

#### `backend/documentation`

| Sin | What it flags |
|---|---|
| `ArchaeologyComment` | A comment that narrates the code's past — `// previously...`, `// changed from...`, `// now it returns...`. |
| `BloatedDocblock` | A class whose docblock runs to multiple paragraphs. |
| `CeremonyDocblock` | A docblock that only restates the typed signature — `@param Type $x` with no description on an already-typed parameter, plus maybe a bare `@return Type`. |

#### `backend/enums-with-behaviour`

| Sin | What it flags |
|---|---|
| `ConstClassEnum` | A class that is nothing but scalar constants — a closed set of values hand- rolled as `const STATUS_PENDING = 'pending'` instead of a native backed enum. |
| `EnumCaseOrChain` | `$x === Status::Pending \|\| $x === Status::Paid` — a hand-rolled membership test against two-or-more cases of the same backed enum. |
| `EnumValueMatch` | A `match`/`switch` over a backed enum's `->value` at a call site — the enum unwrapped to a scalar so it can be dispatched on out here. |
| `InArrayMirrorsEnum` | `in_array($x, ['a', 'b', …])` whose literals ARE an existing backed enum's case values — testing membership of a set the type already seals. |
| `MatchDefaultReturnsNull` | A `match` whose `default` arm returns `null`/`false`/`[]` instead of throwing. |
| `StringMatchMirrorsEnum` | A `match`/`switch` whose arm conditions are string/int literals that ARE an existing backed enum's case values — dispatching on the loose strings instead of the type that already seals them. |

#### `backend/exceptions`

| Sin | What it flags |
|---|---|
| `GenericException` | Throwing a generic SPL/base exception (`throw new \RuntimeException(...)`) instead of a named domain exception. |
| `MessageAtThrow` | `throw new X("…message…")` — the failure described with a prose string at the throw site instead of a named factory carrying domain VALUES (`throw OrderNotFound::forId($id)`). |
| `SwallowCatch` | A `catch` that swallows the failure into absence — an empty body, or whose only effect is `return null/false/[]`. |
| `WrappingWithoutCause` | Throwing a new exception inside a `catch` without passing the caught one on as its cause (`previous`) — the original failure and its stack trace are dropped, so the wrapped error lies about where it came from. |

#### `backend/fix-at-the-source`

| Sin | What it flags |
|---|---|
| `DuplicateFunction` | Two-or-more functions/methods with an identical AST — the same code copy-pasted, down to a formatting-blind structural hash (spacing, newlines, and comments are ignored; only real code differences count). |
| `ManufacturedFakeFill` | Filling an argument with a manufactured fake on absence — `name: $row['name'] ?? ''`, `(int) ($row['id'] ?? 0)`. |
| `NearDuplicateFunction` | Two-or-more functions/methods with the same SHAPE but not identical text — the same control-flow skeleton differing only in variable names or literal values (a type-2 clone). |

#### `backend/guard-clauses-and-flow`

| Sin | What it flags |
|---|---|
| `DeepNesting` | An `if` nested three-deep — a pyramid of conditions. |
| `IfElseLadder` | An `if`/`elseif` ladder of four-plus branches — a chain of conditions doing the job of a `match`, a method on the type, or polymorphic dispatch. |
| `InlineThrow` | A `?? throw` buried inside a larger expression — fed into a call or dereferenced on the same line instead of guarded at the top. |
| `LoopInvertedGuard` | A loop whose entire body is wrapped in one `if` — the iteration's real work pushed a level deep behind a condition. |
| `NestedTernary` | A nested / chained ternary — `$a ? $b : ($c ? $d : $e)` — folds a branching decision into one unreadable expression where the operator precedence is a trap. |
| `RedundantElse` | An `else` after an `if` branch that already exits (`return`/`throw`/`continue`/ `break`). |

#### `backend/pass-the-object`

| Sin | What it flags |
|---|---|
| `ParamResolvedFromParam` | A method that UNPACKS its target out of a container parameter — takes a container object AND a scalar key, resolves the key against the container (`request(Workflow $workflow, string $nodeId)` doing `$workflow->graph->nodeById($nodeId)`), and works on the resolved target while the container is only ever packaging. |

#### `backend/role-vocabulary`

| Sin | What it flags |
|---|---|
| `NullableRegistryLookup` | A class's own keyed store handing back `null` on a miss — `return $this->items[$key] ?? null`. |

#### `backend/tell-dont-ask`

| Sin | What it flags |
|---|---|
| `FeatureEnvy` | Exiled behaviour (feature envy) — a method that reaches THROUGH one other owned object's structure, iterating its collection, to do work that belongs ON that object (`$node->edges()`, not `EdgeDetector::detect($node)`). |
| `KeyedLookupEnvy` | Feature envy through an indirect lookup — a method that uses an owned object's identity as a KEY to fetch data about it through a collaborator, then reads a fact back (`$this->registry->get($node->key)->reservedOutputNames`). |

#### `backend/type-honesty`

| Sin | What it flags |
|---|---|
| `MaskedInvariant` | `$this->scratch?->call() ?? false` — defaulting a reach into the object's own TRANSIENT nullable state. |
| `ScratchStateRestore` | A method that SAVES one of its own properties to a local and RESTORES it afterwards — `$prev = $this->scope; … $this->scope = $prev;`. |

#### `backend/value-objects`

| Sin | What it flags |
|---|---|
| `ArrayBag` | An `array` parameter read by a string-literal key (`$bag['total']`) — a structured bag that should be a typed value object. |
| `ArrayReturnBag` | Returning a multi-field, string-keyed array literal — a structured bag that should be a typed value object. |
| `DataClump` | The same three-or-more value parameters (`string $shopId, string $userId, string $channelId`) threaded through two-or-more signatures in different classes. |
| `PositionalTupleReturn` | Returning a positional TUPLE — `return [$node, $key, $inputs, $outputs]` (also from a closure / arrow fn) — bundles several independent values as a keyless list the caller must destructure by position. |
| `RawDecodedArrayReturn` | Returning a freshly-decoded payload straight out of a boundary — the raw `array` from `json_decode(...)` crossing back into the app untyped. |

#### `backend/concurrent-state`

| Sin | What it flags |
|---|---|
| `ConcurrentSubclass` | A class that `extends` the `jessegall/concurrent` package's `Concurrent` proxy — inheriting the thread-safe shared-state wrapper instead of composing it. |

#### `backend/laravel-idioms`

| Sin | What it flags |
|---|---|
| `ConfigRead` | Reading configuration with `config(...)` inside a class instead of injecting a typed config object. |
| `ContainerReach` | Reaching into the container with `app()` / `resolve()` from a class the container itself resolves — the dependency belongs in the constructor. |
| `FacadeCall` | A Laravel facade call — `Cache::get(...)`, `Log::info(...)`. |
| `MassUpdateAtCallSite` | A mass `->update([...])` on an Eloquent model at a call site — an untyped array of attributes smuggling a mutation past the model's own methods. |
| `ModelMutationAtCallSite` | Setting an Eloquent model's properties at a call site and then `->save()`-ing it — the mutation belongs behind an intention-revealing method on the model (`$order->markPaid()`), not smeared across the caller. |
| `RawRequestInput` | An untyped read off a Laravel request — `->input(...)`, `->get(...)`, `->query(...)`, `->post(...)` on a `Request`/`FormRequest`/MCP request. |
| `RequestAccessorRecast` | A typed request accessor immediately re-flattened to a bare string — `$request->string($k)->toString()` or `(string) $request->string($k)`. |

#### `backend/spatie-data`

| Sin | What it flags |
|---|---|
| `AllNullableData` | A Spatie Data class whose every promoted field is NULLABLE. |
| `DataMethodHintCollision` | A Spatie `Data` class with a `@method` docblock tag that names a method the class ACTUALLY declares — e.g. |
| `ManualHydrationLoop` | `<Data>::from(...)` called per item of a collection — inside a `foreach`/`for`/ `while` loop, or as an `array_map` callback. |
| `NewDataObject` | Constructing a RICH Spatie `Data` object with `new` instead of `::from()` — the raw `new` skips the work `::from()` does: a cast, a name map, a nested-Data hydration, or a magic `fromX()` factory. |
| `NonFinalData` | A Spatie `Data` class that is not declared `final`. |

### Frontend

#### `frontend/vue-components`

| Sin | What it flags |
|---|---|
| `CompoundInlineComponent` | A compound UI primitive assembled INLINE — a component (`<Dialog>`, `<Card>`, `<Sheet>`, `<Tabs>`) whose family parts (`DialogContent`/`DialogTitle`/`DialogFooter`) are filled with a substantial body right here in the parent template, instead of living in its own component. |
| `DeepDataReach` | A CLUSTER of deep data reaches that share one nested object — an element binding or interpolating `order.customer.name`, `order.customer.email`, … from several places in a sizeable template. |
| `DeepNested` | A template nested far too deep — an element many levels in that still has several more levels of markup beneath it. |
| `DuplicateElement` | Two-or-more identical blocks of template markup — the same tags, attributes and children, copy-pasted (the comparison is by STRUCTURE, blind to formatting, whitespace and line numbers). |
| `PropDrilling` | Prop DRILLING — a prop threaded through a component that doesn't use it, on its way to a child that doesn't either. |
| `PropMutation` | A component WRITES one of its own props — `v-model="open"` bound to a prop, or an event handler assigning to it (`@click="confirmingClose = true"`). |

#### `frontend/vue-control-flow`

| Sin | What it flags |
|---|---|
| `ControlFlowOnElement` | A control-flow directive — `v-if` / `v-else-if` / `v-else` / `v-for` — sitting on a real element or component instead of a `<template>`. |
| `IndexAsKey` | A `v-for` whose `:key` is the loop INDEX — `v-for="(item, index) in items" :key="index"`. |
| `LoopWithCondition` | A `v-for` and a `v-if`/`v-else-if` on the SAME element. |
| `SwitchCase` | A `v-if` / `v-else-if` chain whose every branch tests the SAME value against a different case — a switch wearing conditionals. |

<!-- END: detectors -->

## Auto-fixing

Most fixes are domain-specific: the skill teaches the discipline, and your coding
agent reads it and applies the fix at the source — that's the whole point, the tool
is built to *drive an agent*, not to hand you a chore. But some sins have a single,
mechanical correct fix, and for those the tool ships a **scribe**.

A scribe is a small, deterministic rewriter: it edits the parsed **syntax tree**, not
the text, so the change is exact and formatting-safe. There are two kinds — *whole-tree
maintenance passes* that always run, and *per-sin fixes* tied to a specific detector
(both are listed below). The `repent` command runs them all until nothing changes.

For example, a backend `LoopInvertedGuard` — a whole loop body wrapped in an `if` —
is rewritten to a `continue` guard so the body stays flat:

```php
// before                                    // after (repent)
foreach ($rows as $row) {                     foreach ($rows as $row) {
    if ($row->valid()) {                          if (! $row->valid()) {
        $this->import($row);                          continue;
    }                                             }
}
                                                  $this->import($row);
                                              }
```

…and a frontend `SwitchCase` — a `v-if`/`v-else-if` chain re-testing one value — is
hoisted into a `<SwitchCase>`, one slot per case:

```vue
<!-- before -->
<span v-if="status === 'paid'" class="badge badge-green">Paid</span>
<span v-else-if="status === 'pending'" class="badge badge-amber">Pending</span>
<span v-else class="badge">Unknown</span>

<!-- after (repent) -->
<SwitchCase :value="status">
  <template #paid>
    <span class="badge badge-green">Paid</span>
  </template>
  <template #pending>
    <span class="badge badge-amber">Pending</span>
  </template>
  <template #default>
    <span class="badge">Unknown</span>
  </template>
</SwitchCase>
```

`<SwitchCase>` is a tiny utility component the package **provides**, and `repent`
**scaffolds it for you automatically** the moment a fix introduces it — so the rewritten
tree compiles, no extra step (see [Scaffolding](#scaffolding)).

### Running `repent`

```bash
# preview every auto-fix as a unified diff — nothing is written
vendor/bin/commandments repent src --dry-run

# apply them
vendor/bin/commandments repent src
vendor/bin/commandments repent resources/js
```

<!-- BEGIN: scribes (auto-generated — run `composer readme`) -->
_`repent` auto-fixes 13 sins, plus 2 whole-tree maintenance passes._

### Maintenance passes

Whole-tree PHP rewrites, run on every `repent`:

| Scribe | What it does |
|---|---|
| `DataHintScribe` | Brings a Spatie `Data` class's magic surface in line with the spatie-data skill. |
| `RedundantReturnTypeScribe` | Strips a redundant explicit return type from a single-expression arrow function when the expression PROVABLY yields exactly that class. |

### Backend

| Sin | The fix `repent` applies |
|---|---|
| `WrappingWithoutCause` | When wrapping a caught exception, pass the original as `previous`/cause — never drop the stack trace. |
| `LoopInvertedGuard` | Use a `continue` guard so the loop body stays flat; don't wrap the whole body in an `if`. |
| `NestedTernary` | Unfold a nested/chained ternary into a `match` or guards; don't hide branching in `$a ? $b : ($c ? $d : $e)`. |
| `RedundantElse` | Drop the `else` after an `if` branch that already returns/throws/continues/breaks. |
| `ManualHydrationLoop` | Hydrate a collection with `#[DataCollectionOf]` + `::collect()`, not a per-item `::from()` loop. |
| `NewDataObject` | Build a rich `Data` object via `::from()`/a `fromX()` factory, never `new`. |
| `NonFinalData` | Seal a Data class `final` with `readonly` promoted props — it's a leaf, not a base. |

### Frontend

| Sin | The fix `repent` applies |
|---|---|
| `CompoundInlineComponent` | Lift a compound primitive (`Dialog`/`Card`/`Sheet`/`Tabs`) assembled inline into its own named component. |
| `DeepDataReach` | Pass the mid-object as a prop; don't reach deep into nested data from the template. |
| `DeepNested` | Extract a far-too-deeply-nested subtree into its own component. |
| `DuplicateElement` | Extract repeated identical markup into one component. |
| `ControlFlowOnElement` | Put `v-if`/`v-for`/`v-else`/`v-else-if` on a `<template>`, never directly on an HTML or component tag. |
| `SwitchCase` | Dispatch on a value with `<SwitchCase :value>` (a slot per case); never a `v-if`/`v-else-if` chain re-testing the same subject. |
<!-- END: scribes -->

`repent` keeps applying scribes until nothing changes (a fixpoint), so one run
fully converges — and `--dry-run` shows exactly what an apply would produce.

It takes the same **scope** flags as `judge`, so you can auto-fix just what you
touched:

```bash
vendor/bin/commandments repent src --changes            # only working-tree changes
vendor/bin/commandments repent src --branch             # only branch changes vs main
vendor/bin/commandments repent src --branch=develop     # ...vs a different base
```

The whole tree is still parsed (so cross-file rewrites stay correct); only the
files that get written are scoped.

## Scaffolding

Some fixes need a **reusable construct** to point at — a no-op invokable to default an
optional callback to, a `<SwitchCase>` component to hoist a `v-if` chain into. Rather
than make you hand-write it, the package ships it as a stub and generates it into your
project:

```bash
# generate every helper the applicable sins need (idempotent — existing files are skipped)
vendor/bin/commandments scaffold

# ...or just one sin's construct
vendor/bin/commandments scaffold --sin=switch-case
vendor/bin/commandments scaffold --sin=nullable-callback --dry-run
```

A scaffold lands in the right root for its kind — a PHP helper under your PSR-4 source
root with your namespace injected, a Vue component under `resources/js` — and is
**never overwritten**, so it's safe to re-run and safe to edit afterwards.

`scaffold` and `repent` compose — and `repent` runs `scaffold` **for you**: when a fix
introduces a construct (rewriting a `v-if`/`v-else-if` chain into `<SwitchCase>`),
`repent` mints that component in the same run, so the result compiles. Because scaffolding
is idempotent, running `scaffold` yourself is only needed when you want the construct
*before* repenting.

## Developing detectors

A rule of your own is three small classes — a **skill** that teaches the fix, a
**detector** that finds the sin, and (optionally) your own **AST vocabulary** so the
detector reads like a built-in. Before you start, load the project skills via Claude
Code's Skill tool: **`writing-detectors`**, **`detector-engine`**,
**`detector-fixtures`**. The rule throughout: classify by what the AST/type **is**,
never by a name or a hardcoded list.

### A skill

The teaching half — the `SKILL.md` an agent reads to fix a sin. Each is its own class
under `Skills/{Backend,Frontend}/` (auto-discovered by `Skills\Catalog`);
`composer sins` renders it to a `SKILL.md`, with the bad→good block pulled straight
from the fixture:

```php
namespace App\Commandments;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class VehicleAssembly extends Skill
{
    public function __construct()
    {
        parent::__construct(slug: 'vehicle-assembly', tier: Tier::Mandatory, order: 1);
    }

    public function title(): string       { return 'Vehicle assembly — wire the wheels'; }
    public function description(): string { return 'WHEN to build a vehicle clause: always through Vehicle::assemble(), which attaches its wheels and defaults.'; }
    public function intro(): string       { return 'A clause is only whole once it has wheels — building one raw skips the assembler that attaches them.'; }
    public function summary(): string     { return 'assemble clauses via Vehicle::assemble(); never `new` them raw.'; }
    public function principle(): string   { return 'The assembler is the single place a clause becomes road-worthy: it wires the wheels, the defaults, the invariants. A raw `new` ships a clause that looks built but rolls on nothing.'; }
}
```

### A detector

A detector is a few lines of fluent AST query that reads like a sentence — a
selector opens it, `where`/`reject` narrow it (one check per line), a terminal
returns the matches. It references a **sin** (its own class under `Sins/`, which
names the skill that fixes it). `FacadeCallDetector` flags a Laravel facade call,
then peels off every legitimate exception:

```php
namespace App\Commandments;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
// a PHP detector; a Vue one implements JesseGall\CodeCommandments\Frontend\Detector
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Sin;

final class FacadeCallDetector implements Detector
{
    // the sin it points at (names the skill + description)
    public function sin(): Sin { return new FacadeCall(); }

    public function find(Codebase $codebase): array
    {
        return $codebase
            // every `X::y(...)`
            ->whereStaticCall()
            // ...that's a facade
            ->where(fn (AstNode $n) => $n->staticCallClassStartsWith('Illuminate\\Support\\Facades\\'))
            // not `Mail::fake()` — a test double
            ->reject(fn (AstNode $n) => $n->staticCallMethodIs('fake'))
            // not in a route/config file
            ->reject(fn (AstNode $n) => $n->isOutsideClass())
            // not in a provider
            ->reject(fn (AstNode $n) => $codebase->extends($n->enclosingClassName(), 'Illuminate\\Support\\ServiceProvider'))
            ->get();
    }
}
```

No list of facade names — it matches the framework's facade *namespace*, resolved
from the file's imports.

A rule that only makes sense with a particular package declares that on its **sin**,
via `RequiresPackage` — on a project without the package it's filtered out entirely
(never runs, never shows in `--list`):

```php
namespace App\Commandments;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\RequiresPackage;
use JesseGall\CodeCommandments\Sins\Sin;

// RequiresPackage lives on the SIN, not the detector
final class RawCarbonParse extends Sin implements RequiresPackage
{
    public function __construct()
    {
        parent::__construct(
            name: 'raw-carbon-parse',
            skill: DateHandling::class,
            description: 'Carbon::parse() on a raw string — build the date through a typed factory instead',
            rule: 'Build dates with CarbonImmutable::createFromFormat(); never Carbon::parse() untrusted input.',
        );
    }

    // a Composer name for a backend sin (an npm name for a frontend one). No nesbot/carbon
    // in the project → this rule is skipped: it never runs, lists, or reports.
    public function requiredPackage(): string { return 'nesbot/carbon'; }
}

final class RawCarbonParseDetector implements Detector
{
    public function sin(): Sin { return new RawCarbonParse(); }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStaticCall()
            ->where(fn (AstNode $n) => $n->staticCallClassStartsWith('Carbon\\'))
            ->where(fn (AstNode $n) => $n->staticCallMethodIs('parse'))
            ->get();
    }
}
```

### Your own AST vocabulary

Want `$n->isBareVehicleClause()` to read as cleanly as a built-in predicate? Subclass
`NodeMatch`, add the domain predicate composed from the engine's helpers, and **type-hint
it in the `where` closure**. The engine reads the parameter type by reflection and hands
that closure your node — `->where(fn (VehicleNode $n) => $n->isBareVehicleClause())` just
works, no wiring. You can define **as many decorator nodes as you like**; each detector
gets whichever it type-hints (exactly how the built-in `LaravelNode`, `SpatieDataNode`, …
work — one node per package). Because the node also carries the `Codebase`, a predicate can
answer whole-program questions (`$this->codebase->extends(…)`). Here it is with its sin
(pointing at the `VehicleAssembly` skill above) and detector:

```php
namespace App\Commandments;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Sin;

// the node — a domain predicate composed from the engine's helpers
final class VehicleNode extends NodeMatch
{
    public function isBareVehicleClause(): bool
    {
        // a `new App\Vehicles\…Clause(...)` — built raw, so it never declares its wheels
        $class = $this->newClassName() ?? '';

        return str_starts_with($class, 'App\\Vehicles\\') && str_ends_with($class, 'Clause');
    }
}

// the sin — points at the VehicleAssembly skill above
final class BareVehicleClause extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'bare-vehicle-clause',
            skill: VehicleAssembly::class,
            description: 'A vehicle clause built with `new` — it never declares its wheels',
            rule: 'Assemble a clause with `Vehicle::assemble()` so its wheels are wired; never `new` it raw.',
        );
    }
}

// the detector — composes the decorated node's predicate
final class BareVehicleClauseDetector implements Detector
{
    public function sin(): Sin { return new BareVehicleClause(); }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNew()
            // reads like a built-in
            ->where(fn (VehicleNode $n) => $n->isBareVehicleClause())
            ->get();
    }
}
```

Then register the detector in your config. (Type-hinting `VehicleNode` in the closure is
enough for the engine to inject it, so `decorate()` is optional — use it to *declare* your
nodes, register several at once, and set the first as the global default wrap.)

```php
// .commandments/config.php
return fn (Config $config) => $config
    // optional — declare your vocabulary (register as many nodes as you like)
    ->decorate(\App\Commandments\VehicleNode::class, \App\Commandments\GarageNode::class)
    // the detector that speaks it
    ->register(\App\Commandments\BareVehicleClauseDetector::class);
```

### Teaching the engine about a package

Sometimes a *general* rule needs to know a fact about a **framework** — that a class is a
request handler, an entry point, a boundary — so it doesn't false-positive on it. The
built-in feature-envy rule, for instance, must not flag a controller action that reaches
into its `Request`. But a general rule may **not** name a framework (that's the whole
point of keeping it general), and it can't know about a framework *you* wrote a detector
for. That's what a **`Package`** is: the one place a package declares cross-detector facts,
and the general rules read them from the registry — without ever naming your framework.

A `Package` is its own class under `Packages/` (auto-enrolled, like everything else). It
overrides only the hooks its framework needs — each declares one kind of fact, and each
is read by the general rules it applies to. Nothing is a blanket "skip"; every hook is a
narrow, specific exemption:

| Hook | What it declares | Read by (and what it exempts) |
|---|---|---|
| `boundaryTypes(): list<class-string>` | Framework **entry-point** bases — an HTTP/RPC request, the point where raw input crosses into your domain. | **feature-envy** (don't tell you to move behaviour *onto* a request) · **pass-the-object** (a method *taking* one is a boundary, allowed to unpack input from it). |
| `contractMethods(): array<class-string, list<string>>` | Base class → the **methods** a subclass MUST declare, whose shape/array-return the framework dictates (`rules`, `schema`, `casts`). | **near-duplicate** (the shared skeleton across subclasses is inherent, not copy-paste) · **array-return-bag** (the mandated array isn't a bag). |
| `arrayReturningTypes(): list<class-string>` | Bases whose **whole job** is handing the framework arrays (a `FormRequest`, an MCP tool). | **array-return-bag** (a subclass's array returns are contractual — class-level, robust to hooks a rule can't enumerate). |
| `noContainerTypes(): list<class-string>` | Bases/contracts the framework **instantiates itself**, with no container or DI (an Eloquent cast). | **array-bag** (a loose array parameter is the framework's calling convention, nothing to inject). |

```php
namespace App\Commandments;

use JesseGall\CodeCommandments\Packages\Package;

final class AcmeRpcPackage extends Package
{
    // any method taking one of these is a boundary — feature-envy et al. leave it alone,
    // without the general rule ever hearing the name "Acme"
    public function boundaryTypes(): array
    {
        return [\Acme\Rpc\Endpoint::class, \Acme\Rpc\Handler::class];
    }

    // Acme handlers declare a schema() array by contract — don't flag the shared shape,
    // and don't call the array a bag
    public function contractMethods(): array
    {
        return [\Acme\Rpc\Handler::class => ['schema']];
    }
}
```

That's exactly how the built-in `LaravelPackage` exempts `Request`/`FormRequest`/MCP handlers,
`rules()`/`schema()`/`casts()`, and Eloquent casts: it declares the facts, and the general
detectors read `Packages\Catalog` — they never mention Laravel. Every hook is optional; a
package declares only the ones that apply.

## License

MIT.
