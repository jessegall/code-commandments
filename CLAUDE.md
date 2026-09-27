# code-commandments — guide for AI agents

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
the right layer (never inline in the detector). This is not a walk-only rule — it applies
every time you start building.

**`Codebase` (`src/Ast/Codebase.php`) — whole-program.** Selectors open a query:
`whereClass`, `whereInterface`, `whereString` (a literal — the words a USER reads), `whereField`, `whereMethod`, `whereMethodDeclaration`, `whereNew`,
`whereNewExtending`, `whereStaticCall`, `whereFunction`, `whereGetterHook`, `whereAssign`,
`whereParamType`, `whereAttribute`, `whereComment`, `whereClassExtending`, `whereFile` (the shared
`Files\FileQuery` — a rule judges a file's NAME, same selector on both engines), `where(Closure)`.
Graph/resolve: `extends`, `implements` (the WHOLE contract graph — parent chain and interface-extends), `isEnum`, **`isValueType`** (value vs service — walks
the chain), `classNamed` (a class decl), **`declarationMatch`** (ANY class-like incl. enum, with
its file), `index()` (call graph → `callersOf`), `valueFlow()` (field-nil provenance).

**`Query` (`src/Ast/Query.php`).** `where`/`reject` (one check/line), `isUsedOn($fqcn)`,
`withinClass`/`notWithinClass`, `inProximityOf`; terminals `get`/`locations`/`count`/`first`.

**`AstNode` / `NodeMatch` (`src/Ast/`) — per-node, ~120 predicates. Skim before adding one.**
Navigation `parent`/`coalesceLeft`/`coalesceRight` (null-object, never null); reads
`fields()` (→ `ClassField`), `publicFieldNames`, `constructorParams`, `arguments`,
`enclosingClass(Name)`, `enclosingFunction(Name)`, `scope`, `asField`, `staticCallClass/Method`,
`newClassName`, `callName`, `assignedPropertyName`; field-usage `selfPropertyGroupsAssembled`,
`selfPropertiesTestedForAbsence`, `selfFieldNestedReachPairings`,
`rewritesSelfPropertyOutsideConstructor`; predicates `isThrow`, `isInEnum`, `isWithinLoop`,
`isNull`, `everyConstructorParamNullable`, `structuralHash`, … `NodeMatch` adds `line`,
`location`, `near`, `span`, **`trace()`** (a variable's whole journey), `resultIsDeNulled`,
`receiverMutatedNearby`.

**`TypeName` (`src/Ast/TypeName.php`).** `class`, `nullableClass`, `isNullable`, `isNullableArray`,
`render` (type→comparable string), `unionIncludes($fqcn)`.

**`Ast\Support\*` — cross-cutting analyses (memoised per codebase; each is the SINGLE home of its
concept).** `TypeResolver` (`typeOf` — an expression's real type through the receiver chain +
local assignments; `propertyTypeOf`, `collectionElementOf`, `declaringClassOf`), `ChainResolver`
(a property/method chain's final type), `ReceiverResolver` (a call receiver's static type),
`ValueFlow` (field null-flow verdict/explain), `Calls`, `FeatureEnvy`, `LookupEnvy`,
`NullObjectDefault`, `OwnStateMask`, `Frozen`, `Enums`, `StructuralHash`, `PageObject`, `Negation`
(a condition flipped — `!` in front, parenthesised only where that changes meaning), `Docblock`
(`isInline`/`canonical`/`merge`/`foldable` — the SHAPE of a docblock),
`ResponseSurface`, `RouteActions`, `DataClassShape`, `ParamResolution`, `Projection` (is an array
literal the wire shape of a type that ALREADY exists, or an unborn one?). A package's own knowledge
is on its decorator node (`Ast\{Spatie,Laravel,…}\*Node`), stated once.

**Rewriting:** one `Scribes\Writer` (all edits) over `Scribes\Draft`, each edit a `Scribes\Edit`
(half-open `[start, end)`, the ONE `+1` off php-parser's inclusive end in `Scribe::replaceNode`);
the root `Span` owns ALL offset math (incl. `blockOpener` — a new block wears the FILE's brace
style, never the scribe's). `Span` is a shared primitive beside `Codebase`/`Detector`/`Located`,
not a scribe concept — Ast, Vue, Detectors and Scribes all locate through it.
Frontend mirror: `Vue\Codebase`→`Vue\Query`→`ElementMatch`, `Vue\Expr\Parser`.

## Commands

| Command | Purpose |
|---|---|
| `bin/commandments judge [path] [--skill=NAME] [--sin=NAME] [--exclude=A,B] [--changes] [--branch[=BASE]] [--parallel=N]` | Scan a codebase; print sins grouped by the skill that fixes them, and write a `.commandments/sins.md` checklist. Non-zero exit when sins are found. `--changes` reports only sins in files changed/created in the working tree; `--branch[=BASE]` instead scopes to files new/changed on the current branch vs BASE (default `main`) — committed and uncommitted, via the merge-base, no worktree needed. The whole path is still parsed so cross-file detectors stay correct; only the output is scoped. `--parallel=N` runs the detectors across N forked workers (default 2, capped at CPU cores; `--parallel=1` = sequential, also the fallback where `pcntl` is unavailable). |
| `bin/commandments judge --no-checklist` / `--checklist=FILE` | Print only / retarget the checklist file. |
| `bin/commandments judge --list` | List every detector grouped by skill. |
| `bin/commandments hints [path] [--changes\|--branch[=BASE]] [--dry-run[=FILE]]` | Auto-fix Spatie `Data` magic surface: rename non-`from…` object factories to `from<Type>` + rewrite call sites to `::from(...)`, and regenerate `@method from(...)`/`collect(...)` docblock hints. **Default applies; `--dry-run[=FILE]` previews a unified diff.** `--changes`/`--branch` scope to touched files but force **docblock-only** mode (no renames — a rename's call sites can live outside the scope); renaming is whole-tree only. |
| `bin/commandments repent [path] [--changes\|--branch[=BASE]] [--dry-run[=FILE]] [--only=NAME]` | Auto-fix sins — the CLI verb that RUNS the **Scribes** (`src/Scribes/`; "scribe" is the code, `repent` is the command). Two kinds, one command: the maintenance Scribes over the PHP AST (Spatie Data hints; scope-aware) **and** the `Repentable` detectors' scribes — backend (drop a redundant arrow return type, hoist a stray member, regroup a class head, reshape a docblock) and frontend over the Vue components (extract a component, hoist a `v-if` chain to `<SwitchCase>`), each fed its own detector's findings. Default applies; `--dry-run[=FILE]` previews a unified diff; `--only=NAME` runs one rewriter (Scribe or frontend Detector name). (`hints` is the focused Data-only entry.) |
| `bin/commandments report --reason="…" [--ref=PATH:LINE …] [--detector=NAME] [--title="…"] [--global]` | File a GitHub issue (via `gh`): a `[detector-report]` (false positive / wrong rule) when `--detector` is given, else a `[bug-report]`. A bug-report MUST carry its code origin — one or more repeatable `--ref=path:line` (or `path:start-end`); a bug spanning files references EACH, and every ref's source is read and injected into the issue. `--global` opts out only when the defect isn't tied to any file. **A broken/incorrect `repent` (auto-fix) result is itself a bug — report it, referencing both the source and the broken output.** (`--file=/--line=` remain as a single-ref alias.) |
| `bin/commandments feature-request --title="…" --reason="…"` | File a `[feature-request]` GitHub issue proposing a new/changed rule (via `gh`). |
| `bin/commandments layers [path] [--floor] [--write] [--refresh]` / `layers add <Namespace> [--may-use=A,B]` / `layers allow <Layer> <Target>` | Read the dependency stack the project ALREADY has and propose the layer declaration for it (`--write` adds it to `.commandments/config.php`, `--floor` proposes only the bottom). Once a stack is declared the proposal refuses to overwrite it — so a GROWING codebase edits it INCREMENTALLY instead: `add` declares a new layer (or widens one with `--may-use`), `allow` adds a single arrow, and `--write --refresh` regenerates the whole block from today's shape. Every edit goes through the AST ({@see Cli\Config\ConfigFile}) and replaces only the `->layer(...)` chain, keeping the config's own formatting and indentation — never text surgery on a file a formatter has touched. |
| `bin/commandments make <Name> [--engine=backend\|frontend] [--skill=NAME] [--force]` | Scaffold a commandment a CONSUMER project owns: the three classes a rule is made of (a `Skill` that teaches it, a `Sin` that names it, a `Detector` that finds it) written into `.commandments/custom/`, the detector registered in the project's config through the AST ({@see Cli\Config\ConfigFile::registerDetector}), and the REST of the process printed — probe, calibrate, publish — because a scaffold is not a detector yet. `--skill` leniently matches an EXISTING skill (shipped or the project's own) and points the sin at it instead of writing a new one. The consumer-side twin of the package's own detector workflow; the published `writing-detectors` skill teaches it in full. |
| `bin/commandments disable <sin>` / `enable <sin>` | Toggle a rule in the project's `.commandments/config.php`: resolve the sin id (lenient) to its `Sin` class and add/remove it in the `$config->disable(...)` call. Edited through the AST ({@see Cli\ConfigFile}), never text-scanned; the file stays valid PHP and the human's own `register`/`configure` lines are untouched. |
| `bin/commandments install` | Wire a consumer: composer sync hook + the Claude Code hook suite (per-edit rule check, judge nudge, skill nudge) + gitignore, then sync. Every wired hook is stamped so re-wiring never touches the user's own hooks. Idempotent. |
| `vendor/bin/phpunit tests` | The suite — unit tests + the fixture verifier (`FixtureDetectorTest`). |
| `scripts/dev [--mount PATH]… <command>` | Run any Go build/test/vet/run/generate in the capped dev container (3 GB, no swap, 2 CPUs) — never Go on the host. See below. |

**🧾 A consumer writes commandments of its OWN — `.commandments/custom/`.** A project's own `Skill`s,
`Sin`s, `Detector`s and `Package`s live beside its config, in the ONE folder under `.commandments/`
that is neither generated nor session-scoped (so `sync` keeps it out of the folder's `.gitignore` —
those are the project's source). It is deliberately NOT PSR-4 mapped: {@see Custom} discovers the
classes BY FILE (require, then read the declaration list) and `Config::load` requires them before the
config composes, so a `->detector(...)` line can name a class no autoloader knows. Nothing there
auto-RUNS — a detector still earns its place in `$config->detector(...)` — but everything AROUND the
run treats it as a first-class citizen: a project skill is published by `sync` through the very same
{@see Skills\SkillRenderer} as a shipped one, projecting its own sins into its rules and checklist,
and BRIEFED like one — {@see Skills\Catalog} discovers a project's skills beside the shipped set, so the
consumer's CLAUDE.md tier lists name it (marked as the project's own) instead of leaving an agent unaware
that the skill its finding points at exists (#443) — and `judge --list` lists a project's detectors beside the shipped set. It is also always named AS the
project's: a finding from a project-local detector prints `[Name (custom)]` in the console and the
checklist (with a note that the fix belongs in `.commandments/custom/`), and `report --detector=` refuses
to file against it — the package cannot answer for a rule it does not ship ({@see Custom::owns} is the
one ownership test: the class's file lives under the custom folder). **Never build a second,
lesser mechanism for the custom side** — it is the same `Skill`/`Sin`/`Detector`, from a different
folder.

**📖 A command DOCUMENTS ITSELF — never hand-write a usage screen.** Every `Cli\Command` declares
`help(): Help` beside the code that parses its flags — a one-line summary, one `->form(...)` per
subcommand, one `->option(...)` per flag it reads, plus `->note(...)` for the longer prose (a
`HookCommand` takes its summary straight from the hook's docblock; shared flags are `->adopt(...)`ed
from their owner, e.g. `Scope::options()`). EVERY surface is projected from that: `commandments
--help` (the overview, grouped by `Help::section`), `commandments <verb> --help` / `help <verb>`
(the page), a wrong invocation (`HelpScreen::usage($this, "why")` — the ONLY way to fail a command;
never `fwrite(STDERR, "Usage: …")`), the README's command table, and the command references inside
the skills. A skill that teaches a command embeds a block —
`<!-- BEGIN: commands:make (auto-generated, run `composer sins`) -->` (comma-separate several verbs,
or `commands:all`) — which `composer sins` fills from the live CLI; `HelpTest` fails if any skill
block is stale or any command declares no help. So adding a subcommand means adding ONE `->form(...)`
line, and it appears everywhere.

**🤖 An AGENT is a class — `src/Agents/`.** The disciplines are documents, not one assistant's
format, so they are published ONCE into the project's skill library (`.agents/skills/`,
{@see Workspace::LIBRARY}) and every agent is pointed at it: Codex reads that folder natively, Claude
Code gets a per-skill **symlink** into `.claude/skills/` ({@see Agents\SkillLink} — relative on POSIX,
absolute on Windows whose `symlink` resolves a relative target against the process cwd, with a
content-idempotent copy fallback where the filesystem has no links). An {@see Agents\Agent} states only
what actually differs — the folder it discovers skills in, the file it reads instructions from, whether
it `enforces()` via hooks — so a new assistant is a class, never a branch in `Sync`; the catalog is the
agent twin of the sin/detector/skill ones, auto-enrolling like SKILLS (a project's own lives in
`.commandments/custom/`, and `$config->disable(...)` turns one off). **Hooks stay Claude-only** — they
need a harness event protocol and the `$CLAUDE_PROJECT_DIR` anchor — which is the difference between a
discipline that is ENFORCED and one merely written down; say so rather than implying parity.

**🪞 THIS PACKAGE IS A CONSUMER OF ITSELF — the skills we ship are the skills we work under.**
`skills/commandments/**` is the SOURCE; it is not a place any agent can load from. So `sync` runs on
this repo too, publishing the curriculum into our own `.agents/skills/` and linking it where each
agent looks — which is why a `judge` finding here can say "load `commandments-backend-absence`" and
mean it. It is wired to stay current, never re-run by hand: `composer sins` regenerates the sources
and republishes them, composer's `post-install-cmd`/`post-update-cmd` re-sync a fresh checkout,
`composer skills` is the explicit verb, and the pre-commit hook lists `commandments sync` in its
{@see GENERATORS} beside the other generators — because `AGENTS.md` and `CLAUDE.md` are OUR generated
artifacts now ({@see Skills\Briefing} + each agent's `instructions()`), and an edit to the briefing
must not be able to leave them stale. The package's own hand-written skills (`developing-features`,
`writing-detectors`, …) live in that same library, symlinked into `.claude/skills/` and committed —
one home, so they are not Claude-only either. **Whatever you add for a consumer, we get too: verify
it HERE first.**

**⚙️ Where the executable is, is a FACT — {@see Support\Binary}, never a literal.** Every command we
write INTO a project (a wired hook, a `checks` gate, the composer sync call) has to name a file that
is really there. `vendor/bin/commandments` is right for a consumer and wrong for exactly one project
— this one, because composer never shims a package's own `bin` into its own `vendor` — so hardcoding
it meant every hook here failed on every tool call, silently. `Binary::in($root)` answers it once:
the shim when present, the checkout's own `bin/` otherwise, and the shim as the fallback so wiring a
project before its first `composer install` still writes the command that will work.

**📄 The briefing is `AGENTS.md`; `CLAUDE.md` imports it.** {@see Skills\Briefing} renders the canon,
addressed to no agent in particular, into `AGENTS.md`; an agent that cannot read it declares its own
file, and Claude's is `@AGENTS.md` plus what is true only there (the Skill tool, `TodoWrite`, hooks).
The canon is written and verified BEFORE any pointer to it. Both go through {@see Agents\Instructions},
which is the ONE rule about a file the user owns: **inject, never overwrite.** Markers are line-anchored
(our own docs SHOW them, so a quoted one is prose), anything ambiguous — two blocks, a BEGIN with no
END, an END above its BEGIN — REFUSES to write and says why, a file with no block is APPENDED to (never
"after the first line", which lands inside front-matter or an open code fence), line endings/BOM are
preserved, a path resolving outside the project is left alone, and sameness is decided by inode
(`realpath` strings answer `false === false` on a fresh project and ignore case-insensitive collisions).
Writes go through {@see Support\File::write} (temp + rename) under a per-project sync lock.

**Every hook we wire is stamped `@code-commandments-managed`; `sync` strips only stamped hooks,
so a user's own hooks are never touched.**

**🗂 Session state is ONE format, and it NAMES itself — `Cli\State\`.** Every session-scoped state file
(every hook {@see Hooks\Counter}, the touched-sources mark, the session names) is
a {@see Cli\State\StateFile}: `name: value` lines, `-----`, the list the file keeps, `-----`, then the
{@see Cli\State\Legend} that says what every value means and that deleting it is safe. Values are read
and written BY NAME as PHP named arguments — `new State(count: 0)`, `$state->with(count: $n)`
(underscores in code, dashes in the file) — and the legend is the SCHEMA: writing or reading a value it does not declare THROWS
({@see Cli\State\UnknownValue}), so a typo can never land in a file under a name nothing reads back.
**One feature = ONE file.** A count or a flag that belongs to a larger state lives INSIDE it, never in a
file of its own: a count kept beside the state it belongs to outlives it, and the next one inherits it.
A format change is carried across by {@see Cli\Migration}, run once from `sync` — what still has a
reader is MOVED, and the heartbeats and the files of a removed feature are dropped.

**Fixing sins — the checklist workflow.** A full scan is slow (~30s on a large
tree), so judge ONCE, then work the generated `.commandments/sins.md` line-by-line:
read the section's skill, fix the sin at `file:line`, **delete that line**, repeat.
Don't re-run judge between fixes — re-run only at the end to confirm (a clean run
deletes the file).

## Conventions

- **AST/semantic detection over name matching** — always; derive the answer from
  the AST / resolved type, never a class/method/variable name or a hardcoded list.
- **Use the whole arsenal — detectors AND scribes.** Before hand-rolling a scan, reach for the
  engine tools you already have: the call graph (`Codebase::index()` → `callersOf`), the
  provenance/type engine (`TypeResolver::typeOf` — a value's real type through the receiver chain
  and local assignments), the field-nil `ValueFlow`, the variable trace (`NodeMatch::trace()`), the
  field reader (`AstNode::fields()`), and the receiver resolver. This applies to **Scribes too** —
  a repenter has the same arsenal (each finding is a `NodeMatch`/`ElementMatch` with the full node,
  `enclosingClass()`, `fields()`, the codebase via `NeedsCodebase`); compose the engine to gather
  what the fix needs, never scrape source with a regex. A missing predicate is a signal to extend
  the right engine layer.
- **🔧 FIX THE TOOL, NEVER WORK AROUND IT.** When an engine tool gives the wrong answer, the
  bug is in the TOOL — fix it at the source (with a regression test) so every detector that
  uses it benefits. A bespoke workaround inside one detector is a defect, not a solution: it
  hides the real bug, leaves every other caller broken, and rots the engine. If a tool in the
  arsenal is broken, we repair the arsenal. (E.g. `TypeResolver` returning the literal
  `'static'` for a `::for()` factory was fixed IN `TypeResolver`, not skirted in a caller.)
- **A package's AST knowledge lives on its OWN decorator node.** Everything specific to a
  third-party package (Spatie Data, Laravel/Eloquent/MCP, jessegall/concurrent, php-types
  `Option`) is a `NodeMatch`/`ElementMatch` subclass under `Ast\{Laravel,Spatie,Concurrent,PhpTypes}\*Node`
  — the FQCNs stated ONCE — and a detector reaches it by **type-hinting the node in a `where`
  closure** (`->where(fn (LaravelNode $n) => $n->isFacadeCall())`); the shared {@see Query} base
  injects it by reflecting the closure's parameter type (same trick as `Config::configure`), so it
  works on both engines with no wiring. That package's sin + detector + skill live in a per-package
  subfolder (`Backend/Laravel/`, …), auto-enrolled by the recursive catalogs ({@see Discovery}). A
  general detector must NOT reference a package node — if it needs a package concept only as an
  *exemption*, pull it from the node's single source, never redeclare the literal.
- **Overlap is allowed — do NOT strip a detector to avoid it.** One piece of code
  can genuinely be several sins (e.g. set-property-then-`save()` is BOTH
  `ModelMutationAtCallSite` AND read-then-mutate `FeatureEnvy`). Two detectors
  firing on the same `file:line` is correct when both sins are real — each points
  at a different skill/fix. `#[Sinful]` is `IS_REPEATABLE`: a fixture method may
  carry multiple markers (and a detector may have more than 3 marked locations —
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

<!-- BEGIN: agent-journal (auto-generated, run `journal update`) -->

## Rules the journal ships

These come with the journal itself and hold in every project that installs it. They are not this project's opinions; `journal rules` shows the project's own beside them.

**B1 — Never dispatch a subagent without naming its model: haiku for mechanical work with a known answer, sonnet for care without invention, opus only where the task turns on judgement. Unset hands out the orchestrator's own, which is the most expensive model in the room.**

Decide from what the task demands, not from habit.

**haiku** — mechanical work with a known answer: run this, list what matches, apply a stated substitution.
**sonnet** — ordinary work that needs care but no invention: trim comments to one sentence, convert a file to a stated pattern, write a test to a given shape.
**opus** — only where the task turns on judgement: a design call, a review, an ambiguous failure.

The orchestrator's own model is the most expensive one in the room and is almost never the right one for the work it hands out; leaving `model` unset hands out exactly that.

The user naming a model is not an exception to this rule — it is the rule being followed. What it forbids is dispatching without deciding.

<!-- END: agent-journal -->

<!-- BEGIN: agent-journal law (auto-generated, run `journal upgrade`) -->

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

<!-- END: agent-journal law -->
