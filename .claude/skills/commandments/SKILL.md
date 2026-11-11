---
plugin: code-commandments
name: commandments
description: "The index of this project's architectural disciplines — every code-commandments skill and when each one fires. Load this when you start work in this codebase, when you need to know WHICH discipline covers a subject you are about to write, or when a context compaction may have dropped the disciplines you loaded earlier."
---

# Code Commandments — the disciplines in force here

## Skills — load before you work

Code style in this project lives in the code-commandments skills, published to
`.agents/skills/` and loaded **by the exact id in each bullet below** —
`commandments-php-<name>` / `commandments-vue-<name>` (e.g.
`commandments-php-absence`). HOW you load one is your own business: some agents
have a tool for it, some a `/`-command, some want the file read. The id is the same
either way, and so is the rule — read the relevant skill before writing or reviewing
code. Two tiers.

### ⚠️ THE MOST IMPORTANT RULE — TRACE TO THE SOURCE

**Every sin has an origin. Fix it THERE, never where it surfaces.** A finding is a
symptom; before you change a line, trace upstream to where the bad value, missing
type, or wrong shape is BORN, and fix it at that origin. Then the symptom — and
usually several others you hadn't found — disappears on its own.

This is the rule the detectors exist to serve. Honour it on EVERY fix:

- Do NOT silence the symptom. A `?? default`, a cast, a null-check, a wrapper, a
  constructor override, a try/catch, or removing a type to satisfy a detector is
  laundering the problem, not solving it — and it grows the code while hiding the
  smell. That is the wrong move even when the real fix is awkward to reach.
- Ask "where does this value/shape come from?" and walk back until you reach the
  birthplace. Fix is at the birthplace. If the honest fix touches many call sites,
  touch them — that breadth IS the bug surfacing, not a reason to wrap it.

When in doubt, load `commandments-php-fix-at-the-source` and re-read it. It is the parent move
behind every other skill.

**Leave it cleaner than you found it — the gentleman's duty.** When you touch a
file (or even read past a sin while working in it), fix it at the source per the
rule above. Every finding on code you come across is yours to resolve.

**The disciplines.** Each one is a skill, and each says in its own description WHEN to
reach for it — the syntax you are about to write, the decision you are about to make. Load
one when its subject comes up, and load it again rather than working from memory: a
compaction drops instructions silently while leaving you the impression they are still
there. A finding names the skill that fixes it, so you are never guessing.

Load **`commandments-php-fix-at-the-source`** before your first edit whatever else you
do — every other discipline defers to it. And **`commandments`** is this whole list as a
loadable skill, for when you want the map in front of you.

**CONSTANTLY IN PLAY — these fire on ordinary, everyday code, so you will reach for them
most:**

- **`commandments-php-fix-at-the-source`** — the root-cause-first move: trace a value to where it's born, never patch the symptom. Governs how every change is made.
- **`commandments-php-guard-clauses-and-flow`** — validate preconditions at the TOP (early return/throw), flat body, happy path last; never bury a check inline.
- **`commandments-php-value-objects`** — give related data a type: no loose `array<string,mixed>` bags, no data clumps, no primitive obsession. (Decide the type; then `spatie-data` is how to write it.)
- **`commandments-php-spatie-data`** — how to write and construct Spatie `Data` classes — `::from()` not `new`, total types, sealed and readonly.
- **`commandments-php-spatie-data-hydration`** — construct and consume `Data` objects without re-doing what the class declares — pass raw input, let `::from`/casts/collections build.
- **`commandments-php-laravel-idioms`** — typed request/bag access (never raw `->input()`/`->get()`), required constructor DI (never `app()`/facade), Eloquent scopes + intention-revealing model mutation methods.
- **`commandments-php-page-objects`** — the composed `Data` a controller returns for a page — container-injected + `#[Hidden]` collaborators, computed slots over a fat constructor, transformers for output shape.
- **`commandments-php-documentation`** — concise, present-tense docs; rare inline comments; never narrate the past.
- **`commandments-php-absence`** — modelling a value that might be missing (`?T`, `Option`, `null`, empty, Null Object, throw).
- **`commandments-php-class-layout`** — state at the top — traits, constants, properties and hooks above the constructor, methods after.
- **`commandments-php-method-mood`** — commands are imperatives (`hide()`), state predicates are questions (`isHidden()`).
- **`commandments-php-type-honesty`** — a type must not lie: don't fake optionality — a `?T` the design always has set, then defended with `?->`/`?? <fake>` or stashed as save/restore scratch state. Make the type certain (pass it, hold it non-nullable, a per-call value object). The complement of `absence`.
- **`commandments-php-route-actions`** — route actions are thin, single entry points — no controller wrapping another, no duplicate actions, no two routes to one action.
- **`commandments-php-repeated-call-helper`** — a repeated `with`-style call passing the same named argument belongs as a named method on the receiver's type.
- **`commandments-python-flow`** — check preconditions at the top and leave (`return`/`raise`/`continue`), keep the body flat, no `else` after an exit, dispatch instead of an `elif` ladder over one subject.
- **`commandments-csharp-flow`** — check preconditions at the top and leave (`return`/`throw`/`continue`), keep the body flat, no `else` after an exit, a `switch` expression or polymorphism instead of an `else if` ladder over one subject.
- **`commandments-python-absence`** — decide absence where the value is born — raise, return an empty collection, or a Null Object — instead of an `X | None` every caller re-checks; never `or ""` a required value.
- **`commandments-python-value-objects`** — give related data a type — a frozen dataclass — instead of a dict with string keys passed around, or values that always travel together.
- **`commandments-csharp-absence`** — decide absence where the value is born — throw, return an empty collection, or a Null Object — with nullable reference types saying honestly what may be missing; never `?? ""` a required value, never `!` to silence the compiler.
- **`commandments-csharp-value-objects`** — give related data a type — an immutable `record` built at the edge — instead of a dictionary read by fixed string keys, or values that always travel together.
- **`commandments-python-fix-at-the-source`** — trace a value, an effect or a piece of state to where it starts, and fix it there.
- **`commandments-python-type-honesty`** — a type must not lie: don't fake optionality with `| None` a value never is, or keep per-call scratch state on `self`.
- **`commandments-csharp-fix-at-the-source`** — trace a value, an effect or a piece of state back to where it starts, and fix it there.
- **`commandments-python-class-layout`** — state at the top — constants, class attributes and fields above `__init__`, methods after.
- **`commandments-csharp-type-honesty`** — a type must not lie: no `T?` a value never is, no `!` to silence the compiler, no per-call scratch state kept on the instance.
- **`commandments-python-method-mood`** — commands are imperatives (`hide()`), state predicates are questions (`is_hidden()`).
- **`commandments-csharp-class-layout`** — state at the top — constants, fields and stored properties above the constructor, methods after.
- **`commandments-csharp-method-mood`** — commands are orders (`Hide()`), state checks are questions (`IsHidden`, `HasParent`).
- **`commandments-python-repeated-call-helper`** — a keyword call, a guard or a type check written the same way at 2+ sites belongs as one named method on the type it is about.
- **`commandments-csharp-repeated-call-helper`** — a call, a guard or a type check written the same way at 2+ sites belongs as one named member on the type it is about.
- **`commandments-go-flow`** — guard at the top and leave, keep the body flat, no `else` after a branch that already left, and a switch over a defined type rather than a ladder of comparisons on one subject, which no rule enforces and the skill says so.
- **`commandments-python-documentation`** — concise, present-tense docstrings; rare comments; never narrate the past.
- **`commandments-csharp-documentation`** — short, present-tense doc comments; rare comments; never narrate the past.

**ON CONTACT — load the moment the work touches the subject:**

- **`commandments-php-exceptions`** — throwing or catching: named `::for()` factory exceptions, never swallow a failure.
- **`commandments-php-enums-with-behaviour`** — a closed set of values: seal it as a native backed enum, put the per-case logic on the enum (not a `match` at every call site).
- **`commandments-php-role-vocabulary`** — a keyed store / membership set / first-match dispatcher: name it `*Registry`/`*Set`/`*Resolver`, extend the base, honour the contract.
- **`commandments-php-tell-dont-ask`** — behaviour belongs with its data (feature envy): don't exile a loop over one object's collection into a separate class — move it onto the object (`$node->edges()`, not `EdgeDetector::detect($node)`). A Strategy over flat scalar fields is the exception.
- **`commandments-php-pass-the-object`** — demand the resolved type you need, not an id plus its container: a method that takes `(Workflow $workflow, string $nodeId)` then unpacks `$workflow->graph->nodeById($nodeId)` should take the node — the caller resolves once and passes the object (and owns the not-found failure).
- **`commandments-php-concurrent-state`** — state shared across requests/workers (`::for($id): Concurrent<self>`).
- **`commandments-php-templates`** — a multi-line string is a heredoc that SHOWS its output, never a list of line fragments joined.
- **`commandments-vue-vue-components`** — extract a component when template markup REPEATS, or when an element reaches DEEP into nested data — pass it the mid-object as a prop.
- **`commandments-vue-vue-control-flow`** — dispatch on a value with `<SwitchCase :value>` (a slot per case), never a `v-if`/`v-else-if` chain re-testing the same subject.
- **`commandments-php-dependency-direction`** — a declared layer may only reference the layers it declared it may use — down the stack, never back up, never sideways; the direction is enforced from the project's own layer declaration.
- **`commandments-php-behaviour-per-method`** — a parameter that picks WHICH behaviour runs means two methods share one name — split them and let the call site say which it wants, instead of passing a bare `true`.
- **`commandments-vue-mirrored-server-type`** — a hand-written TS type that mirrors a backend Data class is a duplicated contract — mark the Data class `#[TypeScript]`, generate the type, and import the generated one.
- **`commandments-typescript-absence`** — model absence honestly — one spelling for missing, no `??` that invents a value, no `?.` on something always set.
- **`commandments-typescript-duplication`** — a function body written twice becomes one shared function or composable, parameterised by what differs.
- **`commandments-typescript-flow`** — turn away what cannot go on at the top, keep the body flat, and never change a collection while walking it.
- **`commandments-python-duplication`** — a function body written twice becomes one shared function, parameterised by what differs.
- **`commandments-csharp-duplication`** — a method body written twice becomes one shared method, parameterised by what differs.
- **`commandments-python-exceptions`** — raise named exceptions built by a classmethod factory, never swallow a failure, and keep the cause with `raise … from`.
- **`commandments-csharp-exceptions`** — throw named exceptions built by a static factory, never swallow a failure, and keep the cause as the inner exception.
- **`commandments-python-enums`** — a closed set of values is an `Enum` or `StrEnum` carrying the per-case knowledge as methods, not string constants compared at every call site.
- **`commandments-csharp-enums`** — a closed set of values is an `enum`, its per-case knowledge in one exhaustive `switch` expression beside it — not string constants compared at every call site.
- **`commandments-python-behaviour-per-method`** — a parameter that picks WHICH behaviour runs means two functions share one name — split them and let the call say which it wants, instead of passing a bare `True`.
- **`commandments-csharp-behaviour-per-method`** — a parameter that picks WHICH behaviour runs means two methods share one name — split them and let the call say which it wants, instead of passing a bare `true`.
- **`commandments-python-templates`** — a multi-line string is a triple-quoted f-string that SHOWS its output, never a list of line fragments joined.
- **`commandments-python-tell-dont-ask`** — behaviour belongs with its data: move a loop over one object's collection onto that object, and replace an `isinstance` ladder with a method each type answers.
- **`commandments-csharp-templates`** — a multi-line string is a raw string literal that SHOWS its output, never a list of line fragments joined.
- **`commandments-csharp-tell-dont-ask`** — behaviour belongs with its data: move a loop over one object's collection onto that object, and replace a type `switch` with a member each type answers.
- **`commandments-python-dependency-direction`** — a declared layer may only import the layers it declared it may use — down the stack, never back up, never sideways, and never in a cycle.
- **`commandments-python-pass-the-object`** — demand the resolved object you need, not an id plus its container — the caller resolves once and passes the object (and owns the not-found failure).
- **`commandments-csharp-dependency-direction`** — a declared layer may only use the layers it declared it may use — down the stack, never back up, never sideways, and never in a cycle.
- **`commandments-python-role-vocabulary`** — a keyed store / membership set / first-match dispatcher: name it `*Registry`/`*Set`/`*Resolver` and honour the contract — a registry `get` raises on a miss.
- **`commandments-csharp-pass-the-object`** — demand the resolved object you need, not an id plus its container — the caller resolves once and passes the object (and owns the not-found failure).
- **`commandments-csharp-role-vocabulary`** — a keyed store / membership set / first-match dispatcher: name it `*Registry`/`*Set`/`*Resolver` and honour the contract — a registry `Get` throws on a miss.
- **`commandments-comments-only-on-types`** — Only a type carries a comment here, saying what it is for; read before writing any comment in this project. _(this project's own — `.commandments/custom/`)_
