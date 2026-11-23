# code-commandments — guide for AI agents

**code-commandments is a compiler for architecture.** It judges a PHP, Vue, TypeScript,
Python, C# and Go codebase against a set of architectural disciplines and reports each
violation ("sin") as a `file:line` that points at the skill which teaches the fix. It is
one static Go binary; each language brings only a small bridge that parses it.

Two layers:

- **Skills** (`skill/<engine>/`, rendered into `skills/commandments/<engine>/<slug>/`) —
  the teaching layer, one per architectural subject, split by engine: `php/absence`,
  `shared/flow`, `vue/vue-components`, `python/absence`, `csharp/flow`, … The
  engine-prefixed slug is what a sin points at. The source of truth for what good looks like.
- **Rules** (`rules/<engine>-<subject>/<name>.json`) — one JSON file per sin, in the format
  `commandments:neutral-rule/v2`: the sin's name and aliases, the skill slug that fixes it, the
  one-line description the docs project from, and the `find` that locates it over the generic
  tree. A rule has no fix logic. Every file under `rules/` is embedded in the binary and enrols
  itself; `rule/` loads (`library.go`), evaluates (`evaluate.go`) and registers (`detector.go`)
  them, and a project's own rules in `.commandments/custom/` go through the same loader.

Rules are proven against a self-checking fixture per language (`tests/Fixtures/backend`,
`frontend`, `python`, `csharp`, `go`) where the markers — `#[Sinful(Sin::class)]` in PHP, a
`<!-- @sin Name -->` comment in Vue, a `# @sin Name` or `// @sin Name` comment in Python, C#, Go
and TypeScript — ARE the test spec; `fixture/rules_test.go` proves every rule on every one.

### One engine, a bridge per language — non-negotiable

Every language reaches the engine the same way: its **bridge** parses it with the
language's own parser (php-parser for PHP, TypeScript's compiler for Vue and TypeScript,
mypy for Python, Roslyn for C#, go/types for Go) and writes the **generic tree**
(`contract/CONTRACT.md`): one node shape, named roles, one set of type, symbol and comment
facts. The engine loads every stream into one `engine.Codebase`, and a rule reads it the
**same way whatever the language**: its `find` selects neutral kinds and reads their roles and
facts. A language supplies plain facts only, in its bridge; it never decides a rule. If a rule
needs to ask one language differently from another, the tree is wrong — fix the bridge and the
contract, not the rule.

**The engines are the SAME system; ONLY the parse differs.** Everything that is not "how do
I parse" is engine-agnostic: the CLI (`cli/`), the report and checklist, the rule evaluator
(`rule/`), the fixture harness (`fixture/`), the recurrence and twin analyses
(`engine/recurrence.go`, `engine/twins.go`), the syntax hash (`engine/hash.go`, with each
language's `HashRules`). **NEVER write the same machinery twice for two engines** — lift it
into `engine/` or a shared package, parameterised by the one fact that genuinely differs.

### 🌐 Every rule is written ONCE, over the generic tree — non-negotiable

A rule is a concept, not a language. It is written once as a JSON file over the generic tree,
and every language it applies to judges it through that one `find` (its `engine` list). A
language keeps ONLY its own facts in its bridge: how it spells a node kind, a comment, a
module, a test file. "Build this rule for PHP" means focus on PHP, never write the concept only
for PHP. A check two rules share goes into a definition (`rules/definitions/`), never a second
copy. Sir Jesse, messages 19187, 19394, 19404, 19408.

### 🔒 One cache for every checkout, keyed on contents — locked by a test

Every checkout of a project, the main one and every worker's worktree, reads through ONE cache keyed
on a file's contents, never its location. A file any checkout already read is served from that cache
in every other checkout, and a worktree's own changes never evict what the main checkout keeps (a
cache a parser keys by module name, like mypy's, is held per checkout so versions never overwrite
each other, under the project's shared folder whatever run starts the bridge). A kept line is keyed on the
bridge's own code, never the whole binary, so a release that changes no bridge keeps every line
(`TestEveryCheckoutReadsThroughOneCacheKeyedOnContents`). Each checkout takes its own reading turn, so a
worktree never waits on the main checkout's read. Judging is keyed the same way: a rule's answer for a file stands on the file's contents and the
contents of the files its bridge read, so an edit re-judges only that file and the files that import it
(`engine/remembered.go` and `rule/remembered.go`, locked by `TestARuleReadingAcrossFilesIsAskedAgainOnlyForTheChangedFileAndItsReaders`).
A test that fails when a worktree run re-reads a file the main checkout already read
must exist and pass before ANY change to the reading path ships. Breaking this has made single-file
edits take 40-60 s instead of seconds, more than once.

### 🚫 NO regex for structure — read the tree instead

Reaching for a regex to read code structure (a member chain, a method call, a binding, an
equality, nesting depth) is the wrong choice: every language arrives parsed by its own
compiler. **Read the tree.** If the fact you need isn't there, the bridge writes it and the
contract documents it. Regex (`matchesText`) is for genuine text scanning only (a comment's
words, a string literal's contents) — not for understanding the code.

## ⚠️ Building or changing a rule? LOAD THESE SKILLS FIRST — mandatory

Before you write or touch any rule, load these via the **Skill tool**:

1. **`writing-detectors`** — author a rule file end-to-end (start here).
2. **`detector-engine`** — what a `find` reads: the generic tree's kinds, roles and facts,
   the evaluator's operators, pipelines over program relations, shared definitions, and
   where a missing fact belongs (the bridge).
3. **`detector-fixtures`** — the self-checking fixture: `#[Sinful]` = spec, `#[Fixed]` =
   the RESOLUTION the docs publish as "Good" — **and every declaration it moved behaviour
   into**, since a fix showing only the call site that got thinner teaches a reader to call
   a method nothing declares (distinct from `#[Righteous]`, which is a look-alike the
   rule must not flag — usually an exemption, not a fix), the ≥3-diverse-scenarios
   rule, righteous twins.

They encode the cardinal rules: **tree and type signals over name/suffix matching**
(a name check is a smell to justify); **one check per line of a `where`**; try it first
(`commandments rule try`, `commandments rule explain`); ≥3 genuinely-different fixtures plus
a righteous twin it must NOT flag, in every language it judges; and **validate on a real
codebase for false positives** before shipping. Curate the best rules — don't pad.

### ⛔ Calibrate against a real codebase — MANDATORY before any rule ships

A green fixture proves the rule *can* fire; it does **not** prove it's right. A
rule is not done until it has been run against a real-world codebase and its
hits read by eye:

```
bin/commandments judge ../some-app/src --sin=your-sin --no-checklist
```

Open the flagged `file:line`s and judge each **against the skill/the architecture —
NEVER against what the target project happens to do.** A real codebase is not ground
truth: it contains real sins, code done wrong, and old style. So a widespread
pattern there is **not "convention" that excuses a finding** — and **volume alone
proves nothing**: 400 hits can be 400 genuine sins (e.g. an app that never marks its
DTOs `final`). Do not soften or drop a rule because it fires a lot.

The ONLY thing that invalidates a rule is a genuine **false positive** — a
pattern that is *correct under the architecture* yet gets flagged. When those
appear, **tighten the `find` with a principled check (never a name list), drop the
language from its `engine` list, or drop the rule entirely.** Some ideas die here: if
no tree signal separates the sin from a *legitimately valid* look-alike (the difference
is only author *intent*), the rule is not viable — cut it. For example, a named
constructor like `Money::zero()` is indistinguishable from a mis-prefixed factory, so a
rule that would flag it can't tell the two apart and isn't viable. Calibrate every
time, not "later".

**Calibrating a rule that isn't ready to ship? Mark it unpublished.** A new rule often
needs several calibrate→tighten rounds before it's clean. Put `"unpublished": true` in its
`sin` block and every catalogue skips it, so it stays out of `judge`, the fixture verifier,
the generated docs (`SKILL.md`/README) and every release while you iterate. Try it directly
with `commandments rule try <file> <path>` over a scratchpad copy of a consumer codebase.
When its hits read clean on real code, delete the flag, add the ≥3-diverse fixtures +
righteous twin, and it ships. The flag lives IN the file — there is no second list, and a
half-built rule can never leak into a tagged release.

📍 **Sins are first-class.** A sin is the `sin` block of its rule file (name, aliases, skill
slug, description, rule, suggestion). `aliases` keep every former name a config, a filed report
or a fixture marker may still use. `judge --sin=<name>` filters to it, and the generated
`SKILL.md` "when it fires" rows project from the rule files.

## The arsenal — CHECK THIS BEFORE YOU IMPLEMENT ANYTHING

The single most-repeated mistake is hand-rolling logic that already exists. **Before you
implement ANY feature — a rule, a check, a type read, a "does X reference Y" walk — find it
here first.** Reuse it; if it's genuinely missing, ADD it in the right place. This applies
every time you start building.

**The generic tree (`contract/CONTRACT.md`).** Every kind (`if`, `loop`, `call`, `access`,
`handler`, `function`, `type`, …), its named roles (`then`, `else`, `callee`, `subject`,
`member`, `body`, `parameters`), its facts (name, text, form, type, the symbol it resolves to,
its module and file) and the categories a rule may ask for (`exit`, `choice`, `statement`,
`declaration`, `function-like`). `commandments rule explain <file> --line=N` prints it.

**The evaluator's operators (`rule/operators.go`, `commandments rule schema`).** Logic,
lists, navigation (`ownAncestors`, `ownDescendants`, `enclosing`, `siblings`), resolution
(`declarationOf`, `referrers`, `typeOf`, `isSubtype`, `knownSupertypes`), text, and `proven`
for a check that must not hide what it cannot prove.

**Program relations (`rule/program.go`)** — whole-program rows built once per codebase, read
by a pipeline (`"from": "imports"`): `imports`, `declarations`, `references`, `calls`,
`inherits`, `overrides`, `files`, `divergentTwins`.

**Shared definitions (`rules/definitions/`).** `tree.json` holds the `tree.*` reads every rule
calls (`tree.effective`, `tree.nullish`, `tree.kindOf`, …); a package's own checks live beside
it (`laravel.json`, `spatie.json`, `vue.json`), never in a general rule.

**A package's facts (`rules/packages/<ecosystem>/<package>.json`).** The effects of its calls
(`executes-query`, `mutates-receiver`, `blocks-thread`, …) and the exemptions it registers under
a tag (`boundary`, `contract-method`, …), read by a rule through `exemptedBy` and applied only
when the judged project holds the package.

**Cross-cutting analyses in `engine/`** — each the SINGLE home of its concept, memoised per
codebase: `engine.Recurring`/`NearCopies` (a recurrence across sites), `engine.DivergentTwins`,
the syntax hash (`engine.SyntaxHash`, with each language's `HashRules`), `engine.Layers` (a
declared layer stack). `published` carries the facts one engine publishes for another's rules
(a server type the frontend mirrors).

**No automatic fixes in version 8.** The scribes and `repent` are gone: an agent writes the fix
from the finding and its skill. Automatic fixes may come back later, over the generic tree.

## Commands

The CLI documents itself (`commandments --help`, `commandments <verb> --help`), and the
README's command table is projected from the same help. The ones you use while working here:

| Command | Purpose |
|---|---|
| `bin/commandments judge [path] [--sin=NAME] [--skill=NAME] [--changes\|--branch[=BASE]] [--parallel=N]` | Judge a tree: every engine over whatever the path holds; findings grouped by the skill that fixes them, and a checklist written for the session. `--changes`/`--branch` scope the report (the whole path is still read, so cross-file rules stay right); `--parallel` defaults to 2. Here, always scoped — a flag, or a path (rule 7). |
| `bin/commandments info <sin>` | What a rule flags, why, the fix, an example. |
| `bin/commandments report --reason=… --ref=PATH:LINE` / `feature-request` | File a bug, a false positive, or a rule request through `gh`. |
| `bin/commandments make <Name>` | Scaffold a consumer's own commandment in `.commandments/custom/`. |
| `bin/commandments sync` | Publish the curriculum and the briefing, wire the hooks, migrate old state. Composer runs it after install and update. |
| `bin/commandments roslyn-serve` | The C# bridge kept warm, for a session that starts it by hand. The plugin keeps nothing running: each event runs its script once, and each judge starts the bridges it needs and stops them. |
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

**📄 The briefing is ONE file the tool owns, `COMMANDMENTS.md`; an agent's own file brings it in with ONE line.**
`sync` renders the canon, and what each agent the project uses calls its parts, into `COMMANDMENTS.md` in the
project's root and writes that file whole on every run (`cli/agents`, through `cli/atomic`); it opens on a line that
says so, and a `COMMANDMENTS.md` the project wrote itself is refused, never written over. A file the user owns gets a
single line and nothing else: `CLAUDE.md` the import `@COMMANDMENTS.md`, `AGENTS.md` one sentence that tells the agent
to read it. The line is found by its own text, so there are no markers to work out: a file that lacks it has it
APPENDED, a file that holds it is left as it is, line endings and a BOM are preserved. The marked blocks of earlier
versions are taken out the first time (`cli/block`); a block that is ambiguous — two of them, a BEGIN with no END, an
END above its BEGIN — still REFUSES the write and says why. Where the journal writes the briefing into `JOURNAL.md`,
the file and both lines are withdrawn.

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
tree), so judge ONCE, then work the checklist it writes (the run prints its path) line-by-line:
read the section's skill, fix the sin at `file:line`, **delete that line**, repeat.
Don't re-run judge between fixes — re-run only at the end to confirm (a clean run
deletes the file).

## Conventions

- **Tree and type signals over name matching** — always; derive the answer from the tree's
  kinds, roles and resolved types, never a class/method/variable name or a hardcoded list.
- **Use the whole arsenal.** Before hand-rolling a check, reach for what is already there: the
  resolution operators (`declarationOf`, `referrers`, `typeOf`, `isSubtype`), the program
  relations (`calls`, `imports`, `references`, …), the shared definitions in `rules/definitions/`.
  Compose them to gather what a rule needs, never scrape source with a regex. A missing fact is a
  signal to extend the bridge and the contract.
- **🔧 FIX THE TOOL, NEVER WORK AROUND IT.** When the engine, a bridge or an operator gives the
  wrong answer, the bug is in the TOOL — fix it at the source (with a regression test) so every
  rule that uses it benefits. A bespoke workaround inside one rule is a defect, not a solution: it
  hides the real bug, leaves every other rule broken, and rots the engine. (E.g. the PHP bridge
  listing no outside symbols for a project with no autoloader, so `Stringable` was unknown, was
  fixed IN the bridge, not skirted in a rule.)
- **A package's knowledge lives in its OWN files.** Everything specific to a third-party package
  (Spatie Data, Laravel/Eloquent, jessegall/concurrent, php-types `Option`) is stated ONCE as data:
  its facts and exemptions in `rules/packages/<ecosystem>/<package>.json`, its shared checks in
  `rules/definitions/<package>.json`, its own rules in their own folder (`rules/php-spatie-data/`,
  `rules/php-laravel-idioms/`). A general rule must NOT name a package's types — if it needs a
  package concept only as an *exemption*, it asks for the tag with `exemptedBy`.
- **Overlap is allowed — do NOT strip a rule to avoid it.** One piece of code
  can genuinely be several sins (e.g. set-property-then-`save()` is BOTH
  `ModelMutationAtCallSite` AND read-then-mutate `FeatureEnvy`). Two rules
  firing on the same `file:line` is correct when both sins are real — each points
  at a different skill/fix. A fixture site may carry several markers (and a rule may have more than 3 marked locations —
  ≥3 *diverse* is the floor, not a cap). Never weaken or delete a valid finding
  just because another rule also flags it; double-mark the fixture instead.

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

@JOURNAL.md
