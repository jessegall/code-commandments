---
plugin: code-commandments
name: commandments
description: "The index of this project's architectural disciplines — every code-commandments skill and when each one fires. Load this when you start work in this codebase, or when you need to know WHICH discipline covers a subject you are about to write."
---

# Code Commandments — the disciplines in force here

## Skills — load before you work

Code style in this project lives in the code-commandments skills, published to
`.agents/skills/` and loaded **by the exact id in each bullet below** —
`commandments-php-<name>` / `commandments-vue-<name>` (e.g.
`commandments-shared-absence`). HOW you load one is your own business: some agents
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

When in doubt, load `commandments-shared-fix-at-the-source` and re-read it. It is the parent move
behind every other skill.

**Leave it cleaner than you found it — the gentleman's duty.** When you touch a
file (or even read past a sin while working in it), fix it at the source per the
rule above. Every finding on code you come across is yours to resolve.

**The disciplines.** Each one is a skill, and each says in its own description WHEN to
reach for it — the syntax you are about to write, the decision you are about to make. Load
one when its subject comes up. A finding names the skill that fixes it, so you are never guessing.

Load **`commandments-shared-fix-at-the-source`** before your first edit whatever else you
do — every other discipline defers to it. And **`commandments`** is this whole list as a
loadable skill, for when you want the map in front of you.

**CONSTANTLY IN PLAY — these fire on ordinary, everyday code, so you will reach for them
most:**

- **`commandments-shared-fix-at-the-source`** — the root-cause-first move: trace a value to where it is born, fix it there and delete the symptom, never silence it. Governs how every change is made.
- **`commandments-shared-flow`** — guard at the top and leave, no `else` after a branch that left, a `continue` guard instead of a loop wrapped in an `if`, a dispatch instead of a ladder over one subject, one decision per conditional expression, an `if` for an action, a shared last statement written once after the branches, no code after the block has left, and a parameter that keeps the value it was given.
- **`commandments-shared-absence`** — decide absence where the value is born (throw by name, return an empty collection, give a default that does nothing, or say it in the type) so no caller treats a nullable as present or reads a fake value as data.
- **`commandments-shared-exceptions`** — never swallow a failure, catch only to do something, hand a wrapped failure its cause, and throw a named failure that writes its own message.
- **`commandments-php-spatie-data`** — how to write and construct Spatie `Data` classes — `::from()` not `new`, total types, sealed and readonly.
- **`commandments-php-spatie-data-hydration`** — construct and consume `Data` objects without re-doing what the class declares — pass raw input, let `::from`/casts/collections build.
- **`commandments-shared-duplication`** — write it once, name it, and put it with the thing it is about: no copied or near-copied function, no condition or call repeated across sites, no conversion every caller performs, no path or literal spelled out again, and no hand-kept list of what the code already knows.
- **`commandments-php-laravel-idioms`** — typed request/bag access (never raw `->input()`/`->get()`), required constructor DI (never `app()`/facade), Eloquent scopes + intention-revealing model mutation methods.
- **`commandments-php-page-objects`** — the composed `Data` a controller returns for a page — container-injected + `#[Hidden]` collaborators, computed slots over a fat constructor, transformers for output shape.
- **`commandments-shared-documentation`** — document little and only what the code cannot say: a one- or two-line doc comment in the present tense, a rare inline comment for a non-obvious why, never a restated line, a signature repeated in tags, a history, or a reference to something that is gone.
- **`commandments-shared-value-objects`** — give related data a named type, built complete and never changed: no map read by string keys, no positional tuple, no group of parameters that always travel together, no fields that move as one without a type, and a new value derived instead of a field written.
- **`commandments-shared-enums`** — declare a closed set once as an enum and keep everything keyed off a case on it: no class of constants, no literals repeating the cases, no per-case switch at a call site, no unnamed group of cases, no side table, and no type switch.
- **`commandments-shared-class-layout`** — declare what a class has above what it does, in one fixed order: trait uses, cases, constants, static fields, fields from public to private, computed properties, then the constructor and the methods.
- **`commandments-shared-method-mood`** — name a command in the imperative (`hide()`), and a yes-or-no about the object itself as a question (`isShown()`, `hasParent()`, `canRetry()`); a predicate that takes what it compares against (`contains(item)`) stays third person.
- **`commandments-shared-type-honesty`** — a declaration must not lie: no guard for a case the type rules out, no method that silently fills what it was handed, no per-call value kept as a field, no subtype that refuses its contract, no parameter or private member nothing uses, and no getter that computes nothing.
- **`commandments-php-route-actions`** — route actions are thin, single entry points — no controller wrapping another, no duplicate actions, no two routes to one action.

**ON CONTACT — load the moment the work touches the subject:**

- **`commandments-shared-role-vocabulary`** — a keyed store is a Registry, a membership collection a Set, a first-match dispatch a Resolver: name the class for its role, build it on the one base, and keep the contract the name promises.
- **`commandments-shared-tell-dont-ask`** — put the method on the object whose data it uses: no behaviour kept away from its data, no state rule kept outside the change it guards, no walk through an object's neighbours, and no class that only forwards.
- **`commandments-shared-pass-the-object`** — a signature asks for the subject and the callee works out the rest: no argument derived from another argument, no boolean every caller computes from the same object, and no container plus key where the resolved object would do.
- **`commandments-php-concurrent-state`** — state shared across requests/workers (`::for($id): Concurrent<self>`).
- **`commandments-shared-templates`** — write a fixed multi-line text as one multi-line literal at its real indentation and interpolate only what varies; never a list of line fragments joined by a newline.
- **`commandments-vue-vue-components`** — extract a component when template markup REPEATS, or when an element reaches DEEP into nested data — pass it the mid-object as a prop.
- **`commandments-vue-vue-control-flow`** — dispatch on a value with `<SwitchCase :value>` (a slot per case), never a `v-if`/`v-else-if` chain re-testing the same subject.
- **`commandments-shared-dependencies`** — dependencies point down the declared layers and never in a circle; across a module boundary depend on a contract, inside one do not invent an interface nobody needs or repeat one that exists.
- **`commandments-shared-behaviour-per-method`** — give each behaviour its own named method: a method whose body is one branch on a flag, or on whether an optional argument was given, is two methods sharing a name.
- **`commandments-vue-mirrored-server-type`** — a hand-written TS type that mirrors a backend Data class is a duplicated contract — mark the Data class `#[TypeScript]`, generate the type, and import the generated one.
- **`commandments-vue-server-calls`** — reach the server through one client module and the generated routes; never `fetch` from a component or write a server path by hand.
- **`commandments-comments-only-on-types`** — Only a type carries a comment here, saying what it is for; read before writing any comment in this project. _(this project's own — `.commandments/custom/`)_
- **`commandments-one-folder-funnel`** — Only package walk lists or walks the project's folders; read before listing a folder, walking a tree or reading a folder's entries anywhere in this project. _(this project's own — `.commandments/custom/`)_
