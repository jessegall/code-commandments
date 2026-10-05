# code-commandments — guide for AI agents

<!-- BEGIN: agent-journal, form 2 (auto-generated, run `journal upgrade`) -->

## Where the journal comes first

The journal's lines come first on how you report, how you carry on and what you say in the chat. This file's own safety and deploy rules still stand. The user's own word comes before both.

## The journal's law

These rules ship with the journal and cannot be switched off.

**L1 — Every subagent dispatch names its model and chooses the least expensive model that reliably fits the work.**

Use a fast, economical model for mechanical work with a known answer, a capable general model for careful implementation, and the strongest model only when the task turns on difficult judgement. Inheriting the orchestrator's model is not a model choice. If the dispatch API cannot accept a model, that operation is exempt.

**L2 — Every subagent is bound to a concrete job; never dispatch a generic or default agent.**

Use the most specific available agent type whose declared purpose matches the assignment. On providers without agent types, give the dispatch a concrete task name and bounded prompt. If no suitable specialization exists, keep the work in the main agent instead of manufacturing an unscoped helper.

**L3 — Read narrowly: grep for the line, sed a range, head the file; never print a whole file or long output you do not need.**

Everything a tool returns stays in the context for good and is paid for on every turn after it. Search before you read, read the range you need, and cap output with grep, head or tail. Read a whole file only when you need all of it.

**L4 — Follow-up work goes back to the subagent that did the first part; never start a fresh one on work another already holds.**

A subagent that drew a design, wrote the code or ran the research keeps what it learned. When the user asks for a change to its work, continue that subagent with a message rather than dispatching a new one that has to rediscover everything; start fresh only when the earlier one is gone or the new work is unrelated.

**L5 — Every subagent dispatch names the agent: a human name, a little quirky, that fits its role.**

A name is how the user and the chat tell subagents apart and how they are messaged later; an id or a task line is not a name. Start the dispatch's description with the name, a colon, then the task, such as "Dr. Einstein: profile the slow hooks" or "Coco Rams: draw the plan card". A designer can borrow from famous designers, a researcher from famous scientists, mixed up for fun.

## Rules

- Never run .NET on the host; only in a memory-capped Docker container
- Always scope judge with --changes or --branch; full scans only for parity runs
- Never merge anything into main; Sir Jesse merges go-rewrite to main himself

<!-- END: agent-journal, form 2 -->

**code-commandments is a compiler for architecture.** It judges a PHP, Vue, TypeScript,
Python and C# codebase against a set of architectural disciplines and reports each
violation ("sin") as a `file:line` that points at the skill which teaches the fix. It is
one static Go binary; each language brings only a small bridge that parses it.

Three layers:

- **Skills** (`skill/<engine>/`, rendered into `skills/commandments/<engine>/<slug>/`) —
  the teaching layer, one per architectural subject, split by engine: `backend/absence`,
  `backend/value-objects`, `frontend/vue-components`, `python/absence`, `csharp/flow`, …
  The engine-prefixed slug is what a sin points at. The source of truth for what good
  looks like.
- **Sins** (`sins/<engine>/`) — each its own type: the name, the skill slug that fixes it,
  and the one-line description the docs project from.
- **Sin Detectors** (`detectors/<engine>/`) — thin finders over the engine
  (`engine/`). Each detector finds ONE sin and names it; it has no fix logic. Each
  registers itself in its `init()` (`detectors.Register`), and `registry` imports them all.

Detectors are proven against a self-checking fixture per engine (`tests/Fixtures/backend`,
`frontend`, `python`, `csharp`) where the markers — `#[Sinful(Sin::class)]` in PHP, a
`<!-- @sin Name -->` comment in Vue, a `# @sin Name` or `// @sin Name` comment in Python and C# — ARE the test spec.

### One engine, a bridge per language — non-negotiable

Every language reaches the engine the same way: its **bridge** parses it with the
language's own parser (php-parser for PHP, TypeScript's compiler for Vue and TypeScript,
mypy for Python, Roslyn for C#) and writes the **generic tree** (`contract/CONTRACT.md`):
one node shape, one set of type, symbol and comment facts. The engine loads every stream
into one `engine.Codebase`, and a detector reads it through the **same fluent query**
whatever the language — a selector opens a `Query`, `Where`/`Reject` narrow it (one check
per line), `Get` returns `engine.Match`es that know their `file:line`. A language's own
knowledge lives on its decorator (`php.Node`, `typescript`, `vue`, `python`, `csharp`),
reached with `engine.As(php.Node.IsDeeplyNestedIf)`. If one engine's detector doesn't read
like another's, the engine is wrong — fix the engine, not the detector.

**The engines are the SAME system; ONLY the parse differs.** Everything that is not "how do
I parse / how do I detect / how do I fix" is engine-agnostic: the CLI (`cli/`), the report
and checklist, the fixture harness (`fixture/`), the recurrence and twin analyses
(`engine/recurrence.go`, `engine/twins.go`), the syntax hash (`engine/hash.go`, with each
language's `HashRules`), the scribes' writer (`scribes/`). **NEVER write the same machinery
twice for two engines** — lift it into `engine/` or a shared package, parameterised by the
one hook that genuinely differs. A thing belongs in the engine folder of its concern:
`engine/<lang>`, `detectors/<lang>`, `sins/<lang>`, `skill/<lang>`, `scribes/<lang>`.

### 🚫 NO regex for structure — build an engine tool instead

Reaching for a regex to read code structure (a member chain, a method call, a binding, an
equality, nesting depth) is the wrong choice: every language arrives parsed by its own
compiler. **Query the tree.** If the predicate you need isn't there, add it to the engine —
a method on the language's decorator, a selector on `engine.Codebase` — so detectors
compose it fluently. If the bridge doesn't carry a fact the detector needs, add it to the
bridge and the contract. Regex is for genuine text scanning only (splitting delimiters,
reading a comment's words) — not for understanding the code.

## ⚠️ Building or changing a detector? LOAD THESE SKILLS FIRST — mandatory

Before you write or touch any detector, load these via the **Skill tool**:

1. **`writing-detectors`** — author a `Detector` end-to-end (start here).
2. **`detector-engine`** — the fluent query (`engine.Codebase` → `engine.Query` →
   `engine.Match`, a language's decorator), the call graph, the variable trace, and where
   a new helper belongs (the layering rule).
3. **`detector-fixtures`** — the self-checking fixture: `#[Sinful]` = spec, `#[Fixed]` =
   the RESOLUTION the docs publish as "Good" — **and every declaration it moved behaviour
   into**, since a fix showing only the call site that got thinner teaches a reader to call
   a method nothing declares (distinct from `#[Righteous]`, which is a look-alike the
   detector must not flag — usually an exemption, not a fix), the ≥3-diverse-scenarios
   rule, righteous twins.

They encode the cardinal rules: **AST/semantic signals over name/suffix matching**
(a name check is a smell to justify); **one check per `where()`/`reject()` line**;
TDD (red → green through the language's test builder, e.g. `frontendtest.FromSource`);
≥3 genuinely-different fixtures plus
a righteous twin it must NOT flag; and **validate on a real codebase for false
positives** before shipping. Curate the best detectors — don't pad.

### ⛔ Calibrate against a real codebase — MANDATORY before any detector ships

A green fixture proves the detector *can* fire; it does **not** prove it's right. A
detector is not done until it has been run against a real-world codebase and its
hits read by eye:

```
bin/commandments judge ../some-app/src --sin=your-sin --no-checklist
```

Open the flagged `file:line`s and judge each **against the skill/the architecture —
NEVER against what the target project happens to do.** A real codebase is not ground
truth: it contains real sins, code done wrong, and old style. So a widespread
pattern there is **not "convention" that excuses a finding** — and **volume alone
proves nothing**: 400 hits can be 400 genuine sins (e.g. an app that never marks its
DTOs `final`). Do not soften or drop a detector because it fires a lot.

The ONLY thing that invalidates a detector is a genuine **false positive** — a
pattern that is *correct under the architecture* yet gets flagged. When those
appear, **tighten with a principled `reject` (never a name list), or drop the
detector entirely.** Some ideas die here: if no AST signal separates the sin from a
*legitimately valid* look-alike (the difference is only author *intent*), the
detector is not viable — cut it. For example, a named constructor like
`Money::zero()` is indistinguishable from a mis-prefixed factory, so a rule that
would flag it can't tell the two apart and isn't viable. Calibrate every time, not
"later".

**Calibrating a detector that isn't ready to ship? Mark it unpublished.** A new detector
often needs several calibrate→tighten rounds before it's clean. Give its detector (and
its sin) the method `Unpublished()` — `catalog.Unpublished` — and every catalog skips it,
so it stays out of `judge`, the fixture verifier, the generated docs (`SKILL.md`/README)
and every release while you iterate. Unit-test it by calling the detector **directly**,
and calibrate by running it directly over a scanned consumer codebase (a throwaway probe
under the scratchpad — `scan.Walk([]string{root}, source.Excluded{}).Load()` →
`YourDetector{}.Find(codebase)`). When its hits read clean on real code, delete the
method, add the ≥3-diverse fixtures + righteous twin, and it enrols itself. The marker
lives ON the type — there is no second list, and a half-built rule can never leak into a
tagged release.

📍 **Sins are first-class.** Each sin is its OWN type under `sins/<engine>/` (name + skill
slug + description), registered like a detector. A detector *references* its sin
(`func (ArrayBagDetector) Sin() sins.Sin { return backendsins.ArrayBag{} }`), never declares
one inline. `judge --sin=<name>` filters to it. The generated `SKILL.md` "when it fires"
rows project from the registered sins.

## The engine arsenal — CHECK THIS BEFORE YOU IMPLEMENT ANYTHING

The single most-repeated mistake is hand-rolling logic that already exists. **Before you
implement ANY feature — a detector, a predicate, a scribe, a helper, a type read, a "does
X reference Y" walk — find it here first.** Reuse it; if it's genuinely missing, ADD it to
the right layer (never inline in the detector). This applies every time you start building.

**`engine.Codebase` (`engine/codebase.go`) — whole-program, every language.** Selectors open a
query: `Where`, `WhereKind`, `WhereIs` (a neutral kind: a construction, a block, a null-safe
access …), `WhereNew`, `WhereCall`, `WhereFunction`, `WhereTypeDeclaration`, `WhereAssign`.
`Declarations`, `Program` (the facts the bridge read outside the scan), `Files`, `Of` (one
language). A language narrows it first: `php.In(codebase)`, `csharp.In(codebase)`, …

**`engine.Query` (`engine/query.go`).** `Where`/`Reject` (one check per line), and the terminals
`Get`/`Locations`/`Count`/`First`. A language's predicate joins in as `engine.As(php.Node.IsField)`.

**`engine.Match` (`engine/match.go`) — one node, whatever the language.** Navigation `Parent`,
`Children`, `Child`, `ChildrenIn`, `Descendants`, `Closest`, `Root`, `EnclosingType`,
`EnclosingFunction`; reads `Kind`, `Name`, `Text`, `Written`, `Is`, `Refers`, `Resolves`,
`HasFlag`, `HasModifier`, `CalleeName`, `Comments`, `CommentsAbove`, `IsDocumented`,
`IsWithinLoop`, `SameSyntax`; location `Line`, `Location`, `Scope`, `Span`, `Source`.

**A language's decorator — its own knowledge, stated once.** `php.Node` (~200 predicates:
`IsField`, `IsDeeplyNestedIf`, `IsNamedConstructor`, `BreaksClassLayoutOrder`, …; skim before
adding one), `typescript`, `vue` (an element's tag, directives, props), `python`, `csharp`. A
package's own knowledge — Laravel, Spatie Data, jessegall/concurrent, php-types — lives in its
own package under the engine (`engine/php/laravel`, `engine/php/spatie`, …), never in a general
detector.

**Cross-cutting analyses — each the SINGLE home of its concept, memoised per codebase.** PHP:
`php.IndexOf` (the call graph → callers), `php.TypesOf`/`ExpressionType`/`ReceiverTypeOf` (an
expression's real type through the receiver chain and local assignments), `php.ValueFlowOf`
(field null-flow), `php.Trace` (a variable's whole journey), `php.EnvyOf`/`LookupEnvyOf`,
`php.NullObjectFor`, `php.Negation` (a condition flipped), the `php.Docblock*` family (the SHAPE
of a docblock), `php.Printed`. Every engine: `engine.Recurring`/`NearCopies` (a recurrence across
sites), `engine.DivergentTwins`, the syntax hash (`engine.SyntaxHash`, with each language's
`HashRules`), `engine.Layers` (a declared layer stack). `published` carries the facts one engine
publishes for another's detectors (a server type the frontend mirrors).

**Rewriting (`scribes/`):** one draft per file, each edit a half-open `[start, end)`
(`scribes.Edit`), the source's offset math in `engine.Source`/`engine.Span` (incl.
`BlockOpener` — a new block wears the FILE's brace style, never the scribe's). A scribe gathers
what its fix needs from the finding's `engine.Match` and the codebase, never by scraping text.

## Commands

The CLI documents itself (`commandments --help`, `commandments <verb> --help`), and the
README's command table is projected from the same help. The ones you use while working here:

| Command | Purpose |
|---|---|
| `bin/commandments judge [path] [--sin=NAME] [--skill=NAME] [--changes\|--branch[=BASE]] [--parallel=N]` | Judge a tree: every engine over whatever the path holds; findings grouped by the skill that fixes them, and a checklist written for the session. `--changes`/`--branch` scope the report (the whole path is still read, so cross-file rules stay right); `--parallel` defaults to 2. Here, always scoped (rule 7). |
| `bin/commandments repent [path] [--dry-run] [--only=NAME]` | Run the scribes: the auto-fixes of the `Repentable` detectors and the maintenance scribes. `--dry-run` previews a diff. |
| `bin/commandments info <sin>` | What a rule flags, why, the fix, an example. |
| `bin/commandments report --reason=… --ref=PATH:LINE` / `feature-request` | File a bug, a false positive, or a rule request through `gh`. |
| `bin/commandments make <Name>` | Scaffold a consumer's own commandment in `.commandments/custom/`. |
| `bin/commandments sync` | Publish the curriculum and the briefing, wire the hooks, migrate old state. Composer runs it after install and update. |
| `bin/commandments roslyn-serve` / `journal-serve` | The services the agent journal keeps up: the C# bridge, kept warm; the hooks, answered from one process. |
| `scripts/dev [--mount PATH]… <command>` | Run any Go build/test/vet/run/generate in the capped dev container (3 GB, no swap, 2 CPUs) — never Go on the host. See below. |
| `scripts/dev go test ./the/packages/you/touched` | The suite, scoped while iterating (rule 1). |

`bin/commandments` is the composer shim: in this checkout it runs `bin/commandments-go`, which
`scripts/build` builds in the dev container; in a consumer it runs the release binary for the
installed version, fetched once and checked against the release's `SHA256SUMS`.

**🧾 A consumer writes commandments of its OWN — `.commandments/custom/`.** A project's own skills
(each a folder holding a `SKILL.md`) and detectors (each a rule file, `<Name>.json`: the engine it
judges, the sin it finds, and the query that finds it, composed from the same selectors and checks a
shipped detector composes in code — `rule/`) live beside its config, in the ONE folder under
`.commandments/` that is neither generated nor session-scoped. `cli/custom` reads them. Nothing there
auto-RUNS — a rule earns its place in the config — but everything around the run treats it as a
first-class citizen: `sync` publishes a project skill through the same renderer as a shipped one and
briefs it like one, `judge --list` lists its detectors beside the shipped set, and a finding from one
is always named as the project's (`[Name (custom)]`); `report --detector=` refuses to file against it.
**Never build a second, lesser mechanism for the custom side** — it is the same skill, sin and detector,
from a different folder.

**📖 A command DOCUMENTS ITSELF — never hand-write a usage screen.** Every command declares its
`help.Help` beside the code that reads its flags (`cli/help`) — a one-line summary (`help.Of`), one
`Form` per subcommand, one `Option` per flag, `Note` for longer prose, `Adopt` for flags a shared owner
declares — and EVERY surface is projected from it: `commandments --help` (grouped by section),
`commandments <verb> --help`, a wrong invocation (`help.Usage` — the only way to fail a command), the
README's command table, and the command references inside the skills (a block
`<!-- BEGIN: commands:make (auto-generated, run \`composer sins\`) -->`, filled by `cli/doc/refresh`).
Adding a subcommand means adding ONE `Form`, and it appears everywhere.

**🤖 An AGENT is a type — `cli/agents`.** The disciplines are documents, not one assistant's format, so
they are published ONCE into the project's skill library (`.agents/skills/`) and every agent is pointed
at it: Codex reads that folder natively, Claude Code gets a per-skill **symlink** into `.claude/skills/`
(relative on POSIX, absolute on Windows, with a copy where the filesystem has no links). An agent states
only what differs — the folder it discovers skills in, the file it reads instructions from, whether it
enforces through hooks. **Hooks stay Claude-only** — they need a harness event protocol — which is the
difference between a discipline that is ENFORCED and one merely written down; say so rather than
implying parity.

**🪞 THIS PACKAGE IS A CONSUMER OF ITSELF — the skills we ship are the skills we work under.**
`skills/commandments/**` is the rendered SOURCE; `sync` publishes it into our own `.agents/skills/` and
links it where each agent looks, which is why a `judge` finding here can say "load
`commandments-backend-absence`" and mean it. It stays current without being re-run by hand: the
pre-commit hook (`scripts/hooks/pre-commit`) runs the generators and `sync` and re-stages what they
change, `composer sins` does the same on demand, and composer's install and update re-sync a fresh
checkout — because `AGENTS.md` and `CLAUDE.md` are generated here too. The package's own hand-written
skills (`developing-features`, `writing-detectors`, …) live in that same library, committed.
**Whatever you add for a consumer, we get too: verify it HERE first.** This repo's own config
(`.commandments/config.json`) judges the bridges' own PHP, Python and C# sources.

**⚙️ Where the executable is, is a FACT — `cli/binary`, never a literal.** Every command we write INTO
a project (a wired hook, the composer sync call) has to name a file that is really there.
`vendor/bin/commandments` is right for a consumer and wrong for exactly one project — this one,
because composer never shims a package's own `bin` into its own `vendor` — so `binary.In(root)` answers
it once: the shim when present, the checkout's own `bin/commandments` otherwise.

**📄 The briefing is `AGENTS.md`; `CLAUDE.md` imports it.** The briefing renders the canon, addressed to
no agent in particular, into `AGENTS.md`; Claude's file is `@AGENTS.md` plus what is true only there
(the Skill tool, hooks). Both go through the ONE rule about a file the user owns (`cli/block`,
`cli/agents`): **inject, never overwrite.** Markers are line-anchored (our own docs SHOW them, so a
quoted one is prose), anything ambiguous — two blocks, a BEGIN with no END, an END above its BEGIN —
REFUSES to write and says why, a file with no block is APPENDED to, line endings and a BOM are
preserved, and a write goes through `cli/atomic` (temp + rename).

**Every hook we wire is stamped `@code-commandments-managed`; `sync` strips only stamped hooks,
so a user's own hooks are never touched.**

**🗂 Session state is ONE format, and it NAMES itself — `cli/state`.** Every session-scoped state file
(every hook counter, the touched-sources mark, the session names) is `name: value` lines, `-----`, the
list the file keeps, `-----`, then the legend that says what every value means and that deleting it is
safe. Values are read and written BY NAME, and the legend is the SCHEMA: a name it does not declare is
refused where it is written or read, so a typo can never land in a file nothing reads back. **One
feature = ONE file.** A count or a flag that belongs to a larger state lives INSIDE it. A format change
is carried across by the migration `sync` runs once (`cli/sync`) — what still has a reader is MOVED,
and the files of a removed feature are dropped.

**Fixing sins — the checklist workflow.** A full scan is slow (~30s on a large
tree), so judge ONCE, then work the generated `.commandments/sins.md` line-by-line:
read the section's skill, fix the sin at `file:line`, **delete that line**, repeat.
Don't re-run judge between fixes — re-run only at the end to confirm (a clean run
deletes the file).

## Conventions

- **AST/semantic detection over name matching** — always; derive the answer from
  the AST / resolved type, never a class/method/variable name or a hardcoded list.
- **Use the whole arsenal — detectors AND scribes.** Before hand-rolling a scan, reach for the
  engine tools you already have: the call graph (`php.IndexOf`), the type engine
  (`php.ExpressionType`/`ReceiverTypeOf` — a value's real type through the receiver chain and local
  assignments), the field-nil value flow (`php.ValueFlowOf`), the variable trace (`php.Trace`), the
  field reader (`php.Fields`). This applies to **scribes too** — a scribe has the same arsenal (each
  finding is an `engine.Match` with the whole codebase behind it); compose the engine to gather what
  the fix needs, never scrape source with a regex. A missing predicate is a signal to extend the
  right engine layer.
- **🔧 FIX THE TOOL, NEVER WORK AROUND IT.** When an engine tool gives the wrong answer, the
  bug is in the TOOL — fix it at the source (with a regression test) so every detector that
  uses it benefits. A bespoke workaround inside one detector is a defect, not a solution: it
  hides the real bug, leaves every other caller broken, and rots the engine. If a tool in the
  arsenal is broken, we repair the arsenal. (E.g. the PHP bridge listing no outside symbols for a
  project with no autoloader, so `Stringable` was unknown, was fixed IN the bridge, not skirted in a
  detector.)
- **A package's knowledge lives in its OWN engine package.** Everything specific to a third-party
  package (Spatie Data, Laravel/Eloquent, jessegall/concurrent, php-types `Option`) is stated ONCE in
  its package under the engine (`engine/php/spatie`, `engine/php/laravel`, …), and a detector reaches
  it the same way it reaches the language — `engine.As(spatienode.Node.IsDataClass)`. That package's
  sins, detectors and skill live in a per-package folder (`detectors/backend/laravel/`, …). A general
  detector must NOT reference a package's knowledge — if it needs a package concept only as an
  *exemption*, take it from that package's single source, never redeclare the literal.
- **Overlap is allowed — do NOT strip a detector to avoid it.** One piece of code
  can genuinely be several sins (e.g. set-property-then-`save()` is BOTH
  `ModelMutationAtCallSite` AND read-then-mutate `FeatureEnvy`). Two detectors
  firing on the same `file:line` is correct when both sins are real — each points
  at a different skill/fix. A fixture site may carry several markers (and a detector may have more than 3 marked locations —
  ≥3 *diverse* is the floor, not a cap). Never weaken or delete a valid detection
  just because another detector also flags it; double-mark the fixture instead.

## Go runs ONLY in the capped dev container — `scripts/dev`

`GOMEMLIMIT` is a target, not a cap: a Go test that grows past it takes the machine
down with it. So every Go command an agent runs — `go test`, `go build`, `go vet`,
`go run`, `go generate` — goes through `scripts/dev`, which runs it in a container the
MACHINE caps at 3 GB (no swap) and 2 CPUs, and a run past that is OOM-killed
(`docker/dev/cap_test.go` proves it). Never run Go on the host.

```
scripts/dev go test ./cli/...                 # scope it: only the packages you touched
scripts/dev go run ./cli/parity/record NAME
scripts/dev --mount ../some-app go run ./engine/frontend/parity ../some-app
```

The image (`docker/dev/Dockerfile`: PHP, composer, Go, node, Python with mypy, the
docker CLI) builds itself on the first run and again only when the Dockerfile changes.
Go's caches live in shared docker volumes, so only the first run is slow. The checkout
is mounted at its own path and the host's docker socket beside it, so the Roslyn
bridge a test starts still runs in its own capped container. A folder outside the
checkout is visible only through `--mount` (read-only). Without docker it fails; there
is no host fallback. Anything in `scripts/` or a hook that runs Go calls `scripts/dev`.

<!-- BEGIN: code-commandments skills (auto-generated, run `composer update`) -->
@AGENTS.md

## Working here as Claude Code

The briefing above is the canon, shared with every agent. These are the parts of it
that have a specific name in this harness:

- **Load a skill with the Skill tool**, by the exact id in the briefing's bullets —
  e.g. `commandments-backend-absence`. The published skills are linked into
  `.claude/skills/`, so they also autocomplete as `/`-commands.

**The disciplines here are ENFORCED, not just written down.** Hooks are wired into
`.claude/settings.json`: the cardinal rule resurfaces as you work, `judge` is nudged
before risky commands and on stop. That is a property of this agent alone — under an
agent with no hook protocol the same disciplines are documents you are asked to follow,
and nothing checks that you did.
<!-- END: code-commandments skills -->






