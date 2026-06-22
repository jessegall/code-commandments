# Guide for Claude / AI agents working in this repo

This is **code-commandments** — a static-analysis tool that judges PHP + frontend
codebases against a configurable set of rules ("prophets"). It runs as both a
Laravel artisan command and a standalone CLI.

## Core commands you'll reach for

The CLI lives at `vendor/bin/commandments` (standalone) or
`php artisan commandments:<cmd>` (Laravel). Both variants expose the same
flags.

| Command | Purpose |
|---|---|
| `judge` | Scan the codebase and report sins. Returns non-zero when sins are found. |
| `repent` | Auto-fix findings marked `[AUTO-FIXABLE]` in the judge output — **both sins and warnings**. `[AUTO-FIXABLE]` declares a rewrite safe, which is orthogonal to severity, so an auto-fixable *warning* is repented without bumping the prophet to `sin` (which would also make it block commits). |
| `scripture [--prophet=NAME]` | Print the detailed rule behind a prophet. |
| `absolve` | Absolve a single advisory finding. Target it by `--fingerprint=<hash>` (copied from `judge --next`) OR by `--at=path:line` (or `--at=path:from-to`) — the same locator `judge` prints; `--at` resolves to the finding's content-based fingerprint at scan time (it stores no line number, so it self-heals when the file changes), and `--prophet=NAME` disambiguates ties on a line. Requires `--reason="…"`. Refuses to absolve sins (they must be fixed) and unknown/stale fingerprints. `--all` **baselines the whole queue** (absolve every current advisory finding at once; sins still block). `--warnings` **batch-absolves every WARNING in scope** under one shared `--reason` (optionally narrowed with `--scope=git`/`--scope=staged`, or `--prophet=NAME` to scope to one prophet in a single scan); it **hard-refuses if any sin is in scope** and absolves nothing — no per-finding fingerprint walk. `--until-push` makes an absolution (single or `--warnings`) **sticky**: it survives the post-commit reset and stays until `git push` (the pre-push hook clears it via `--clear-until-push`) — for reasoned LEAVE warnings during an active grind, so the same coincidental warnings aren't re-litigated every commit. `--clear` **removes every ordinary absolution** — used by the post-commit hook so absolutions never silently persist across commits. |
| `sync` | Register newly added prophets into the consumer's `commandments.php`. Pass `--after=<ver>` (or `--after=previous`) to only add prophets introduced after that version — respects prophets you intentionally removed. Also re-asserts the `.gitignore` block for generated tracking state (refreshing it if the package added new state files), so consumers pick up the ignores on package update via the post-merge sync hook. |
| `install-sync-hook` | Install a git post-merge hook that auto-runs `sync --after=previous` when composer.lock changes in an incoming merge. |
| `install-hooks` | (Laravel) **Alias for `profile phased`.** Installs the phased bundle: Claude hooks (session-start scripture/briefing, stop-judge, per-turn drift re-index, and a **PostToolUse** reminder after every `git commit`), a git **pre-commit gate** (blocks commits with sins), **post-commit reset**, **commit-msg guard**, **pre-push reset**, and a **`.gitignore` block** for the generated tracking state. Briefing is hook-delivered — it does NOT write a committed CLAUDE.md section. Idempotent. The standalone `init` does the same. |
| `init` | Create a starter config + install the phased profile (standalone). Alias for `profile phased`; briefing is hook-delivered, no committed CLAUDE.md section. |
| `profile [name]` | Show / `list` / switch the active **work profile** — a named bundle of {git hooks, Claude hooks, agent briefing} that controls how intrusive the package is. Briefing is delivered by the **local session-start hook** (`scripture` / `profile --brief`), never a committed CLAUDE.md section — so switching strips any legacy `## Code Commandments` section, and `sync` cleans it on update (CLAUDE.md carries no commandments knowledge). Profiles are PHP classes (`src/Support/Profiles/`) each returning a `ProfileOptions` value object; the options drive everything (hooks DERIVED from options, never enumerated). Ship: **`disabled`** (default — dormant, no hooks/briefing, agent unaware), **`grind`** (heads-down cadence: NO judge/tests between phases, pre-push gate blocks branch-scoped sins, warnings still flagged; reckon once at the end), **`phased`** (today's face-by-face), **`sins-only`** (phased but warnings silenced). Switching installs the new bundle and tears down the previous one — teardown computed from the blocks ACTUALLY ON DISK, foreign hook content preserved. Selection is local+gitignored (`.commandments/profile`); a legacy consumer (hooks already installed, no state) infers `phased` so an update never silently disables it, but a bare `judge`'s scope only shifts for an EXPLICITLY-selected profile. `--brief`/`--drift-check` are the session-start + per-turn hooks that (re-)index the agent on the active contract. |
| `report` | File a prophet false-positive / wrong-rule as a GitHub issue (via `gh`) on `report.repo` (default the package repo). Requires `--prophet` and `--reason`; optional `--file`/`--line` auto-attach the snippet. Pass `--fingerprint=<hash>` OR `--at=path:line` (the locator `judge` prints) to record a report-linked absolution so the finding stays quiet until the issue is answered — `--at` also infers `--prophet`/`--file`/`--line` from the finding. **Without a locator the report files but records NO absolution (the finding still blocks) — it says so.** Agents run this when a finding is genuinely wrong, so it gets fixed instead of silently absolved. Pass `--feature-request` to instead file an ENHANCEMENT / new-rule **proposal** (no `--prophet`/`--at` needed, `enhancement`-labelled, records no absolution); optional `--title`, `--proposed-prophet`, `--rubric`. |
| `scaffold` | Generate the recommended support classes (Option, FromArrayOnly, NullCallable, CompareSelf, the `Resolver` chain base + the `Predicate` kernel: IsNull/IsEnum/AllOf/AnyOf/Negated) into `scaffold.namespace`, namespace-rewritten and idempotent. A scaffold may carry a `subNamespace` (e.g. predicates land in `Resolvers\Predicates`); relocated copies are refreshed in place by their `@code-commandments-generated` marker. Auto-runs on `sync` unless `scaffold.auto` is false. |
| `install-skills` | Install the on-demand "how to do it right" Claude Code skills (one per architectural subject — option, invariants, registry, null-object, enums, named-exceptions, resolvers, coalesce-factories, immutable-data) into `.claude/skills/commandments/<slug>/` from the packaged `stubs/skills/` tree — the positive teaching layer that pairs with the prophets (enforce) and the scripture (terse rule). Mirrors `scaffold`: `{{ namespace }}` examples are rewritten to `scaffold.namespace` (so they match generated code), idempotent (`--force` to refresh), and `--auto` is a no-op unless `skills.auto_refresh` (which force-refreshes, stamps a do-not-edit banner, and gitignores the tree). Auto-runs on `sync` unless `skills.auto` is false; folded into `install-hooks`/`init`; a session-start hook keeps it current. A prophet finding points back at its skill ("deep dive"). |

## `judge` flags

Each of these narrows what gets scanned. `--file`, `--files`, `--git`, and
`--path` are **mutually exclusive** — pick the one that matches intent.

| Flag | When to use |
|---|---|
| `--scroll=<name>` | Limit to one scroll (e.g. `backend`, `frontend`). |
| `--prophet=<NAME>` | Show only sins from one prophet (partial name match). Useful for focused fixing: `judge --prophet=NoRaw`. |
| `--file=<path>` | Judge one file against all prophets for a scroll. The cross-file index is still built from the **full scroll**, so origin traces and `NeedsCodebaseIndex` prophets resolve against the whole project — `--file` only narrows what gets reported, not what the rules may see. |
| `--files=a.php,b.php` | Judge a small set of files. Honours scroll `path` + `exclude`. |
| `--git` | Judge only files new/changed vs. the git tracked state. Honours scroll `path` + `exclude`. Cross-file index is still built from the full scroll so origin traces resolve callers outside the changed set. |
| `--staged` | Judge only files **staged for commit** (`git diff --cached`). This is what the **pre-commit gate** uses, so an unrelated dirty/branch file can't block a commit that doesn't touch it. Same routing as `--git`. |
| `--path=<dir>` | **Override the scroll's `path` AND bypass every exclude (default + configured).** Use when you need to scan a specific subtree regardless of what the config says. Example: `judge --path=vendor/foo/src --scroll=backend` to run the rules against a vendored package you're reviewing. |
| `--absolve` | Mark emitted warnings as reviewed so future runs don't re-report them (file-level). |
| `--next` | **Guided mode.** Print exactly ONE finding at a time — its location, message, inline advisory rubric, scripture pointer, and fingerprint — ordered most-root-cause-first. The agent fixes it (then re-runs `--next`) or absolves it with a reason. Output is always short, so nothing is lost to truncation. Exits non-zero while findings remain. |
| `--config=<path>` | Point at a custom `commandments.php` (standalone CLI only). |

### When `--path` is the right flag

- You edited code in a directory that's normally excluded (e.g. a generated
  folder, a vendored package, `node_modules`) and want to quickly lint it.
- You want to audit a subtree in isolation without touching scroll config.
- You're investigating a specific prophet's behaviour on known files.

`--path` does not look at scroll `exclude` entries, does not skip `vendor`,
`node_modules`, `storage`, `.git`, or `bootstrap/cache`. Everything under
`<dir>` that matches the scroll's `extensions` gets judged.

## Where prophets and scrolls are configured

- **Prophets** live in `src/Prophets/Backend/` and `src/Prophets/Frontend/`.
  Each extends `BaseCommandment` (or `PhpCommandment` for PHP) and implements
  `judge(string $filePath, string $content): Judgment`.
- **Scrolls** are defined in the consumer's `config/commandments.php`, which
  the package ships as `config/commandments.php` too. Each scroll has a
  `path`, `extensions`, `exclude` list, and ordered `prophets` list.
- Per-prophet config (including `exclude` paths) goes inside the prophet's
  entry: `ProphetClass::class => ['exclude' => ['path/fragment']]`.

## Cross-file call-graph tracing (relevant when writing new prophets)

`ScrollManager` builds a `CodebaseIndex` once per run and injects it into any
prophet that implements `NeedsCodebaseIndex`:

```php
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;

class MyProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }
}
```

The index exposes `classByFqcn()` and `callersOf($fqcn, $method)` for
walking the graph. `OriginTracer` wraps this with a depth-limited upstream
walk used by `NoArrayStringIndexingProphet` to point at where a DTO should
actually be introduced. The index is always built from the full scroll —
`--file`, `--files`, `--git`, and `--staged` narrow which files get
reported, never what the cross-file prophets are allowed to see.

## Authoring prophets: ALWAYS prefer AST/semantic detection over name matching

When a prophet needs to decide "is this the kind of thing I flag?", derive the
answer from the **AST / real type semantics**, not from a class/method/variable
**name** (or a hardcoded suffix/base list). Names are a last resort, used only
when the real signal is genuinely unreachable.

- "Is X a value object I can hoist a factory onto?" → check whether its
  **effective constructor takes an array** (reflection over the inheritance
  chain, AST fallback) — NOT `str_ends_with($name, 'Bag'|'Collection'|'Data')`.
  A name list both over-fires (`FooData` service) and under-fires (a value
  object that doesn't follow the suffix), and silently encodes wrong assumptions
  (e.g. Spatie `Data` is NOT array-constructible — it has typed promoted params).
- "Is this a Data class / a Registry / an Option?" → resolve the type and inspect
  its declaration (extends/implements, attributes, constructor, method
  signatures), walking the chain via reflection or the `CodebaseIndex`, rather
  than trusting the identifier.
- Reflection (`class_exists` + `ReflectionClass`) is fair game in `judge` — the
  consumer's classes (and vendor) are autoloadable at scan time; use it to read
  the *effective* constructor / signature across inheritance. Fall back to AST
  for classes that are not loadable (test fixtures, unscanned code).

If you find yourself writing a `const SOMETHING_BASES = [...]` name list to
classify nodes, stop and ask whether the AST/type already answers it — it almost
always does. A name check is a smell to justify, not a default.

## Authoring advisory & ranked prophets

Findings are no longer just "sin or warning". Four hooks shape how they are
presented and tracked (all default sensibly on `BaseCommandment`, override
per prophet):

- **`advisory(): ?Advisory`** — REQUIRED for any prophet that emits warnings.
  Return `Advisory::make()->applyWhen(...)->leaveWhen(...)->whenUnsure(...)`.
  This rubric is printed inline under the finding in `judge --next`, so the
  agent reads *when the rule applies* without a separate scripture trip. Pure
  sin prophets return `null` (the default).
- **`tier(): Tier`** — ordering altitude (`Structural` → `Correctness` →
  `Convention` → `Cosmetic`). Override `defaultTier()`; honours a `tier`
  config override. `judge --next` walks structural findings first.
- **`supersedes(): array`** — list of prophet classes whose findings are
  *deferred* while one of this prophet's findings sits in the same file
  region (±60 lines). Example: `NoArrayBagProphet::supersedes()` returns
  `[NoArrayStringIndexingProphet::class]` — fix the bag and the string-index
  symptoms usually vanish, so they aren't shown until the root cause is gone.
- **`rootCauses(): array` + the `RootCauseMap`** — the symptom-side inverse of
  `supersedes()`. The invariant/absence family is wired through **one**
  `RootCauseMap` (cause → symptoms); `supersedes()` and `rootCauses()` both
  DERIVE from it on `BaseCommandment`. **To relate two prophets, add ONE edge to
  `RootCauseMap::relations()` — never hand-override both directions on a prophet**
  (that desyncs them and fails `RootCauseMapTest`). `rootCauses()` lets a symptom
  *trigger* its cause even under `--prophet=` filtering (the `RootCauseResolver`
  runs the cause on the symptom's enclosing method and annotates a "fix this
  first" hint) — that the trigger runs a prophet the filter excluded is **by
  design**, not a leak. `repent` consults the same map and **SKIPS** an
  auto-fixable symptom whose root cause is unresolved in-region (reported in a
  SKIPPED bucket), so it can't launder an invariant violation; resolve the named
  cause, then re-run `repent`.
- **Measure & suppress** — when a criterion is *countable*, gate emission on
  it instead of always warning. `PreferOptionOverNullProphet` implements
  `NeedsCodebaseIndex` and stays silent below `min_callers` resolved call
  sites, baking the count into the message. Zero resolved callers = unknown
  (not suppressed). The index is built from the full scroll even under
  `--file`, so single-file runs now resolve callers like any other run; a
  truly absent index (build failed) is treated as unknown, not suppressed.

**Fingerprints & absolution.** Pass a stable `symbol` (e.g. the method label)
as the last arg to `sinAt()` / `warningAt()` so a finding's identity survives
line shifts. `Fingerprint::of(prophetClass, relativePath, symbol, snippet)`
keys finding-level absolution (`absolve --fingerprint=…`), which self-heals:
editing the flagged code changes the snippet → new fingerprint → the old
absolution stops matching and the finding re-surfaces. Stale absolutions are
GC'd on every full `judge` run.

## Triaging `[prophet-report]` issues

ALWAYS read the **comments**, not just the issue body — decisive details (exact
sites, the accepted fix, "this is actually a false positive", scope narrowing)
frequently live in comments. Pull every open report with its body **and** all
comments via:

```bash
composer issues           # = scripts/open-issues.sh — every open issue WITH all comments (one command for all triage)
```

Never triage from a bare `gh issue view N` (body only).

## Commit / release conventions for this repo

- Commit messages: no `Co-Authored-By` trailer.
- Every commit gets a new semver tag: **patch** for small edits / bug fixes,
  **minor** for new features. **Never bump major** — ask the user first.
- Push both the commit and the new tag on the same action.
- **Bypassing the commandments hooks entirely** (e.g. syncing/propagating into a
  consumer while an agent is actively working there): use
  `git -c core.hooksPath=/dev/null commit …` (and the same for `push`). Plain
  `--no-verify` only skips pre-commit/commit-msg — it does NOT skip the
  **post-commit** reset (`absolve --clear`), which would wipe the working agent's
  confessions/absolutions mid-task. `core.hooksPath=/dev/null` disables every git
  hook, so a maintenance commit never disturbs the agent's state.
  `scripts/update-consumers.sh` uses exactly this.

## Self-judge is a TEST HARNESS, not a gate (this repo only)

`commandments.self.php` dogfoods the package against its own `src/`. It exists so
you can eyeball prophet behaviour — **findings it reports against this package's
own source are NOT release blockers, and fixing them is out of scope** unless the
user asks. When committing changes to code-commandments itself, `git commit
--no-verify` is fine for self-judge findings, and you do **not** need a
"righteousness pass" over the repo. This exception is **scoped to THIS
repository** — never tell a *consumer* to bypass their own gate.

## Quick sanity commands

```bash
# Test suite
vendor/bin/phpunit

# Run the tool on itself (useful while authoring prophets)
php bin/commandments judge -c config/commandments.php

# Register newly-added prophets into config
php bin/commandments sync -c config/commandments.php
```
