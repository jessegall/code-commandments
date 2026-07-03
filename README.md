# Code Commandments

> An architecture linter for PHP & Vue — built to drive AI coding agents.

**code-commandments** judges a PHP **and** Vue codebase against a set of
architectural disciplines and reports each violation — a "sin" — as a `file:line` in
your code, grouped under the **skill** that teaches the fix.

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
- [Hooks](#hooks)
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
  *teach*, and — for the mechanically-fixable ones — *scribes* (deterministic
  auto-fixers, see [Auto-fixing](#auto-fixing)) *fix*.

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

## Usage

```bash
# scan — sins grouped by the skill that fixes them. With no path, judge reads the
# source roots from $config->paths(...) in .commandments/config.php — auto-detected from
# your composer.json on the first run (edit it, or run `commandments config reindex`, to adjust scope).
vendor/bin/commandments judge
vendor/bin/commandments judge src                  # ...or point it at a path to override

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

# executing an approved plan (see Hooks below)
vendor/bin/commandments checks start          # run the project's start / phase / complete checks
vendor/bin/commandments checks phase
vendor/bin/commandments checks complete        # full gate — your checks, then `judge --branch`
vendor/bin/commandments plan status            # is a plan active? / plan done — end it
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
        // The source roots judge and repent scan (auto-detected on first run).
        ->paths('app', 'src')

        // Silence a rule by its Sin class (drops the detector that finds it).
        ->disable(NonFinalData::class)

        // ...or by a specific Detector class.
        ->disable(FacadeCallDetector::class)

        // ...or by a whole Skill class (every detector that discipline teaches).
        ->disable(ValueObjects::class)

        // Add a detector that lives in YOUR codebase.
        ->detector(\App\Commandments\NoRawSqlDetector::class)

        // Tune thresholds — name the detector, then set it. Its setters chain, so
        // you can tune several knobs in one closure.
        ->configure(fn (DeepNestedDetector $d) => $d->maxDepth(10)->maxRemaining(2))
        ->configure(fn (DataClumpDetector $d) => $d->minClasses(3));
};
```

Each move is named for what it registers: `paths` (the source roots to scan — auto-detected
on first run from your composer.json, re-runnable with `commandments config reindex`),
`disable` (silence a rule), `detector` (add your own finder), `configure` (tune a threshold),
`package` (register a framework's exemptions — covered under
[Developing detectors](#developing-detectors)), `hook` (register your own Claude Code hook —
see [Hooks](#hooks)), and `planExecution` (the plan-execution profile — see [Hooks](#hooks)).
`configure` uses the closure's **first
parameter type** to find the detector and hand it in, so you tune it by calling its own
methods. Run `commandments config` for a summary of what's in effect.

## Hooks

`install` (and every `composer update`, via `sync`) wires a small set of **Claude Code
hooks** into `.claude/settings.json` — the cardinal-rule reminder, the "did you judge?"
nudge (before a commit / at turn end), and the **plan-execution** hooks. They self-heal:
a hook change reaches every project on the next `composer update`, never freezing at
install time.

**Your own hooks are never touched.** Every command we wire carries a stamp
(`# @code-commandments-managed`). On re-wiring we strip *only* stamped commands and add
the current set back — so a hook you wrote by hand in `settings.json`, even one that
itself runs `commandments`, is always preserved.

### Plan execution

When you approve a plan, a `PostToolUse`/`ExitPlanMode` hook loads the
`executing-plans` skill and injects your project's profile; the agent then branches,
works phase-by-phase (scoped tests + `checks phase`, commit each), and runs the full
gate — `checks complete`, which appends `judge --branch` — **once** at the end. Opt into
`keepGoing()` and a `Stop` hook re-nudges "keep going" until `commandments plan done`
(loop-safe, and self-clears an abandoned plan). All state is read live from config and
scoped to the current git worktree. Configure it:

```php
use JesseGall\CodeCommandments\PlanExecution;

$config->planExecution(fn (PlanExecution $plan) => $plan
    ->branchFrom('main')            // base to cut from + judge --branch base
    ->branchPrefix('plan/')         // the plan branch prefix
    ->pushEachPhase()               // push after every phase (default: once at the end)
    ->keepGoing()                   // Stop hook re-nudges until `plan done`
    ->onStart('composer install')   // once, before the first phase
    ->eachPhase('composer lint')    // after each phase — keep it fast
    ->onComplete('composer test')); // the end gate; judge --branch runs after
```

On `composer update` a starter block is injected automatically, its `onComplete`
inferred from your composer/npm scripts. Edit it freely.

### Register your own hook

Hooks are an **open set**, like detectors. Write a `Hook`, declare where it binds, and
register it — it's wired and run exactly like a built-in (through
`commandments hook '<class>'`), and it carries the same stamp:

```php
use JesseGall\CodeCommandments\Cli\Hook;
use JesseGall\CodeCommandments\Cli\HookBinding;
use JesseGall\CodeCommandments\Cli\HookEvent;

final class AnnounceOnStop extends Hook
{
    public function bindings(): array
    {
        return [new HookBinding('Stop')];
    }

    protected function onStop(HookEvent $event): int
    {
        // …your logic; use $this->block()/$this->inject()/$this->pass()
        return $this->pass();
    }
}
```

```php
// in .commandments/config.php
$config->hook(\App\Hooks\AnnounceOnStop::class);
```

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
// tests/Fixtures/backend/app/Orders/RefundService.php
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
<!-- tests/Fixtures/frontend/components/UserBadge.vue -->
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
_17 skills — full table in [README.skills.md](README.skills.md)._
<!-- END: skills -->

## Sins & detectors

Every sin (the `--sin=` key) and what it flags, grouped by the skill that teaches
the fix and split by engine. Each sin has one detector that finds it, named
`<Sin>Detector` (e.g. `SwallowCatch` → `SwallowCatchDetector`).

<!-- BEGIN: detectors (auto-generated — run `composer readme`) -->
_61 sins across 17 skills — full tables in [README.sins.md](README.sins.md)._
<!-- END: detectors -->

## Auto-fixing

Most fixes are domain-specific: the skill teaches the discipline, and your coding
agent reads it and applies the fix at the source — that's the whole point, the tool
is built to *drive an agent*, not to hand you a chore. But some sins have a single,
mechanical correct fix, and for those the tool ships a **scribe**.

A scribe is a small, deterministic rewriter: it edits the parsed **syntax tree**, not
the text, so the change is exact and formatting-safe. There are two kinds — *whole-tree
maintenance passes* that always run, and *per-sin fixes* tied to a specific detector
(both are listed in [README.scribes.md](README.scribes.md)). The `repent` command runs
them all until nothing changes.

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
_`repent` auto-fixes 13 sins, plus 2 whole-tree maintenance passes — full tables in [README.scribes.md](README.scribes.md)._
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

### Prop types when extracting components

When an extract scribe lifts a chunk into its own component, it types every prop it
generates rather than emitting `defineProps<{ x: unknown }>()`. Types are resolved by
**sound AST inference** first — a `ref`/`computed` literal, a homogeneous literal array
(`[50, 100, 200]` → `number[]`), a destructured composable's return field, a loop
variable's element type, a prop traced up the render tree to where the value originates.
Nothing is guessed: if only a real type checker could resolve it, it stays `unknown`.

If — and only if — the target project already ships **`vue-tsc`**, a last rung asks it
to resolve whatever the AST couldn't (a member-typed `computed`, a `ref(props.x)`, a
composable with an inferred return). This is never a dependency of the package; a project
without `vue-tsc` gets exactly the AST inference and no more. The checker runs
`--incremental` (a cached `.tsbuildinfo`) with `--skipLibCheck`, batched to one run per
component — `repent` isn't interactive, so the extra pass is worth the precision.

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
    public function trigger(): string     { return 'WHEN to build a vehicle clause: always through Vehicle::assemble(), which attaches its wheels and defaults.'; }
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

The node needs **no registration** — type-hinting `VehicleNode` in the closure is enough
for the engine to inject it (that's why the built-in `LaravelNode`/`SpatieDataNode` work
with no config). You only register the detector:

```php
// .commandments/config.php
return fn (Config $config) => $config
    ->detector(\App\Commandments\BareVehicleClauseDetector::class);
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
registers **exemptions** keyed by a **tag** — a class-string the detector and the package
both agree on. It's an open system: a detector reads a tag, any package registers against
it, and neither imports the other. Nothing is a blanket "skip"; every registration is a
narrow, specific exemption.

The built-in tags (`Packages\Tags\*`), and the general rules that read each. Each carries a
**slug** — a short id you can pass to `exempt(...)` instead of the FQCN. Run `commandments
exemptions` to print this list, or `commandments exemptions <sin|detector>` to see just the
tags one detector honours:

| Tag (slug) | What it means | Read by (and what it exempts) |
|---|---|---|
| `Boundary` (`boundary`) | A framework **entry point** — an HTTP/RPC request, where raw input crosses into your domain. | **feature-envy** (don't move behaviour *onto* a request) · **pass-the-object** (a method *taking* one may unpack input from it). |
| `ContractMethod` (`contract-method`) | A **method** a subclass MUST declare, whose shape/array-return the framework dictates (`rules`, `schema`, `casts`). | **near-duplicate** (the shared skeleton is inherent, not copy-paste) · **array-return-bag** (the mandated array isn't a bag). |
| `ArrayReturning` (`array-returning`) | A class whose **whole job** is handing the framework arrays (a `FormRequest`, an MCP tool). | **array-return-bag** (its array returns are contractual — class-level, robust to hooks a rule can't enumerate). |
| `NoContainer` (`no-container`) | A type the framework **instantiates itself**, no DI (an Eloquent cast). | **array-bag** (a loose array parameter is the framework's calling convention). |

A package registers in `register()`, building each tag's clause fluently — `classes()` for
whole classes, `on(class, ...methods)` for specific methods, `methods()` for a name ignored
everywhere:

```php
namespace App\Commandments;

use JesseGall\CodeCommandments\Packages\Exemptions;
use JesseGall\CodeCommandments\Packages\Package;
use JesseGall\CodeCommandments\Packages\Tags\Boundary;
use JesseGall\CodeCommandments\Packages\Tags\ContractMethod;

final class AcmePackage extends Package
{
    public function register(Exemptions $exemptions): void
    {
        // any method taking one of these is a boundary — feature-envy et al. leave it
        // alone, without the general rule ever hearing the name "Acme"
        $exemptions->exempt(Boundary::class)
            ->classes(\Acme\Rpc\Endpoint::class, \Acme\Rpc\Handler::class);

        // an Acme handler's schema() is an array by contract — don't flag the shared
        // shape, don't call the array a bag
        $exemptions->exempt(ContractMethod::class)
            ->on(\Acme\Rpc\Handler::class, 'schema');
    }
}
```

`exempt()` takes the tag class **or** its slug, so `exempt('boundary')` is the same as
`exempt(Boundary::class)` — for a well-known tag you need only know the slug. That's exactly
how the built-in `LaravelPackage` tags `Request`/`FormRequest`/MCP handlers,
`rules()`/`schema()`/`casts()`, and Eloquent casts — it ships inside the package, so it
**auto-enrols** (like every built-in detector, sin, and skill). Your own `Package` lives in
*your* codebase, which the package's glob never sees, so you register it in
`.commandments/config.php` — the same file where you `disable`/`detector`/`configure`:

```php
// .commandments/config.php
return fn (Config $config) => $config
    ->package(\App\Commandments\AcmePackage::class);
```

### Your own exemption tags

A tag is **always** an `Exemption` — so a custom one is its own subclass, naming itself with
a `slug()` and explaining itself with a `description()` (the slug is what packages register
against; the description is what `commandments exemptions` prints):

```php
use JesseGall\CodeCommandments\Packages\Exemption;

final class AcmeEntrypoint extends Exemption
{
    public function slug(): string        { return 'acme-entrypoint'; }
    public function description(): string { return 'An Acme RPC endpoint — exempt from feature-envy.'; }
}
```

Your detector reads it, any package (yours or a third party's) registers against it, and
neither imports the other. Declare it via `Exemptable` so `commandments exemptions
<detector>` can show what quiets it, then read it in `find()`:

```php
use JesseGall\CodeCommandments\Packages\{Exemptable, Exemptions};

final class AcmeFeatureEnvyDetector implements Detector, Exemptable
{
    public function exemptions(): array { return [AcmeEntrypoint::class]; }   // what it honours

    // …inside find():
    ->reject(fn (AstNode $n) => Exemptions::has(AcmeEntrypoint::class, $codebase, $n->enclosingClassName()))
}
```

Two packages can exempt each other's types without either importing the other — they only
share the tag.

## License

MIT.
