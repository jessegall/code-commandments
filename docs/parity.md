# Parity: the Go tool against the PHP tool

The Go tool replaces the PHP tool only if it judges a codebase the way the PHP tool does. This page records that
check on real codebases, one per language the tool reads, each pinned to a commit: how many findings each tool
makes, every finding only one of them makes, and why — and, last, every difference that is no PHP bug, with what a
project that relied on the PHP tool does now.

## How it is checked

- `scripts/memory/snapshot.sh <checkout> <commit> <out>` pins a codebase at one commit, archived without touching the
  checkout's working copy; for a C# solution it adds the restore output, made in a capped container
  (`--memory=4g --cpus=2`), never on the host.
- Each tool judged the snapshot once — the PHP tool and the Go binary built from the same checkout — at
  `--parallel=2` inside the capped dev container, and each finding only one of them made was listed as
  `file:line [Detector]`. The script that did it left with the PHP tool.
- A C# solution too large to hold whole was compared one project at a time by the C# parity test against the PHP
  tool's recorded findings (`engine/csharp/testdata/chronos.findings.each.gz`); koel's frontend the same way against
  `engine/frontend/testdata/koel.findings.gz`, with its one-sided findings listed in
  `engine/frontend/testdata/koel.accounted`. Those recordings stay, so `detectors/*/parity_test.go` still holds the Go
  tool to them.

A difference is settled in one of two ways. A Go bug is fixed in Go. A PHP bug — the PHP tool misreading code that
the language's own parser reads right — is left in the PHP tool, which this switch deletes, and the Go behaviour it
disagrees with is held by a Go regression test, so it cannot drift back.

## Results

| Codebase | Languages | Commit | PHP | Go | Only one tool | Cause |
|---|---|---|---:|---:|---:|---|
| koel | PHP, Vue, TypeScript | 7f321705 | 2,255 | 2,275 | 28 | PHP bugs, below |
| smart-farmers-pos | PHP, Vue, TypeScript | 8ca4e821 | 3,630 | 3,630 | 4 | PHP bugs, below |
| worldwatchmarket/site | TypeScript | 1c8e2c24 | 23 | 23 | 0 | — |
| worldwatchmarket/dullahan | C# | 3631af46 | 92 | 92 | 0 | — |
| worldwatchmarket/chronos, 211 projects one at a time | C# | ac38efd04 | 15,026 | 15,026 | 0 | — |
| agent-journal | Python, Vue | 100ef38a | 397 | 329 | 90 | PHP bugs, below |

Every finding only one tool makes is a PHP bug. None is a Go bug left standing: each Go bug the runs found was fixed
in Go before this table was taken.

## C# on a user's machine, with no Docker

A released tool runs the C# bridge's own executable, fetched from the release on the first C# judge and checked
against `SHA256SUMS` (docs/requirements.md). It was proven in a container standing in for a user's machine — the
release's `commandments-linux-arm64` and `roslyn-bridge-linux-arm64` served as a release, installed as the shim
installs them, and no Docker and no Go toolchain inside — against the findings of the Docker bridge development runs:

| Codebase | Docker bridge | .NET SDK installed | No .NET at all |
|---|---:|---:|---:|
| tests/Fixtures/csharp (its layers written as config.json) | 195 | 195, identical | 135, and one notice |
| worldwatchmarket/dullahan at 3631af46 | 92 | 92, identical | 71, and one notice |

With no .NET installed the framework's types do not resolve, so the rules that read them find less; the run says so
once, and judges the project's own types and its restored packages as usual.

## The PHP bugs, and the Go tests that hold the right answer

| PHP bug | Where it shows | Go test |
|---|---|---|
| A declaration straight after an `import` with no semicolon is swallowed (`src/Ts/Parser.php`). | koel: 12 DefendedCertainField and some Duplicate/NearDuplicateFunction only in Go. | `TestADeclarationAfterAnImportWithNoSemicolonIsRead` |
| An angle-bracket assertion (`<symbol>Key`) loses the rest of its expression. | koel: helpers that differ read as copies (only in PHP), and copies missed (only in Go). | `TestAnAngleBracketAssertionKeepsWhatFollowsIt` |
| An iterable's type is read only from a written annotation, never inferred. | koel: 2 IndexAsKey; agent-journal: 9 IndexAsKey, all only in Go. | `TestAnIterableTypedOnlyByInferenceIsKnownAsAnArray` |
| Any type ending in `]` is taken for an array, so an indexed access to an object type is one. | smart-farmers-pos: IndexAsKey at `ReviewFeeds/Form.vue:134` and `:152`, only in PHP. | `TestAnIndexedAccessToAnObjectTypeIsNoArray` |
| An object literal stops at a shorthand method (`onError (e) { … }`), losing it and the rest of the literal (`src/Ts/Expr/Parser.php`, `objectLiteral`). | smart-farmers-pos: the two `EnrollDialog.vue:25` submit handlers weigh 18, under the near-duplicate floor of 20; NearDuplicateFunction only in Go. | `TestAMethodInAnObjectLiteralIsReadWhole` |
| A file linked into the tree a second time is judged twice. | agent-journal: `install.py` links to `src/install.py` — 54 DuplicateFunction, 24 RepeatedGuard and a second DictReturnBag only in PHP, and `released()`, declared twice, resolves to neither, hiding ConvertedArgument at `auto_update/test.py:244` and `:245` (only in Go; the PHP tool finds both once the link is gone). | `TestAFileLinkedIntoTheTreeIsReadOnce` |

The PHP backends of koel (1,373 findings) and smart-farmers-pos (2,626) agree finding for finding; every difference
there is on the frontend. koel's 28 one-sided findings are listed, each with its cause, in
`engine/frontend/testdata/koel.accounted`.

## The Go bugs the runs found, fixed in Go

- The frontend bridge ran out of memory on worldwatchmarket/site's generated `graphql.ts`: node queued the whole
  output for a slow reader. It now writes each line in pieces and waits for the pipe to drain; a file over a
  mebibyte of source is judged by its syntax without checker types.
- A TypeScript body's shape and weight now follow the PHP engine's own, each pinned to a weight measured there
  (`TestABodyWeighsWhatThePhpEngineWeighs`): a cast reads as the value cast, a template literal is one literal,
  `undefined` is the constant it is, an optional chain `?.` is no copy of a plain one, and a declaration initialised
  by a call of a plain name weighs one more.
- The PHP bridge's class loader required files that do not exist; a probe for a class the parser lacks is no
  failure (`TestAProbeForAClassTheParserLacksIsNoFailure`).

## Migrating from the PHP tool

Every difference left between the two tools that is not a PHP bug is listed here: what the PHP tool did, why the Go
tool does otherwise, and what a project that relied on it does now.

### `make`'s entry in the help overview

The PHP tool summarised `make` as scaffolding "a skill, a sin and a detector in `.commandments/custom/`, registered in
your config"; the Go tool says "a rule naming its sin, and the skill that teaches the fix, in `.commandments/custom/`,
turned on in your config". `make` writes what the Go tool runs — a `<Name>Detector.json` rule and, for a new
subject, a `skills/<slug>/SKILL.md` — where the PHP tool wrote three PHP classes, so the PHP summary would describe
files the Go tool never writes. Every other line of the overview is the PHP tool's, and the CLI parity cases hold `make`'s line
exactly as the Go tool prints it. Nothing changes for a project: `commandments make <Name>` is run as before.

### The `rule` command, and what `sync` publishes of the writing-detectors skill

The PHP tool had no `rule` command: a PHP detector was a class, proven by a fixture and run by `judge`. The Go tool's
rules are data, so it gives their author the tools a class had from its IDE — `rule explain` shows the tree a rule reads,
`rule try` runs one without turning it on, `rule prove` holds each to the samples that mark it, and `rule schema` prints
the schema an editor checks a rule file by — and the help overview lists the command. The writing-detectors skill that
`sync` publishes teaches those tools and every check a rule step can make, so the digest of the skills a project is
given differs from the PHP tool's. Every other line of the overview, and every other file `sync` and `install` write,
is the PHP tool's. Nothing changes for a project: a rule it already has runs as before.

### `has-csharp` in the help overview

The PHP tool kept no C# bridge up, so it had no need to say whether a project has C#. The journal plugin keeps one up
as a service, and the journal asks `has-csharp` before it starts it, so a project with no C# starts none and lists
none as running; the help overview lists the verb. Nothing changes for a project.

### What the briefing says `make` writes

The briefing `sync` publishes into a project's `AGENTS.md` and its `commandments` skill said, from the PHP tool, that
`make` "writes the three classes a commandment is made of — the skill that teaches it, the sin that names it, the
detector that finds it — into `.commandments/custom/`, registers the detector in this project's config". The Go tool's
briefing says `make` writes the `<Name>Detector.json` rule, with the skill that teaches the fix when no existing one
does, and turns the rule on — for the reason `make`'s own summary differs: the PHP wording tells an agent to look for
files the Go tool never writes. Nothing changes for a project: the next `sync` rewrites the block it keeps in
`AGENTS.md`, and the rest of the file is left as it was.

### A project's own PHP detectors, sins and skills

The PHP tool loaded the classes its `make` wrote into `.commandments/custom/` — a `Detector`, a `Sin` and a `Skill`
subclass — and ran them against its own engine. That engine is PHP and is gone with the PHP tool, so the Go tool cannot
run them: it reads a project's own rules as data. Every `.php` file left in `.commandments/custom/` is skipped, and
`judge` and `sync` name each one with what it becomes, so no rule is dropped in silence. A detector the config still
turns on is named as one that could not be loaded.

To carry one over:

1. `commandments make <Name>` scaffolds `.commandments/custom/<Name>Detector.json` and turns it on in `config.json`.
2. Write the detector's query as the rule's `find`: a selector, then one `where` or `reject` check per condition
   (README, "Developing detectors"; the `commandments-writing-detectors` skill lists every selector and check). The
   sin's name, description and rule go in the rule's `sin`.
3. Move the skill's text into `.commandments/custom/skills/<name>/SKILL.md`, the name the rule's `sin.skill` points
   at. The page `sync` last generated from the class, `.agents/skills/commandments-<engine>-<name>/SKILL.md`, front
   matter and all, is the text to start from.
4. Check the rule finds what the class found with `commandments judge --sin=<name>`, then delete the classes.

A check the rule language cannot express is a `commandments feature-request` for the selector or check it needs.

### A project's own agents

The PHP tool let a project add an agent of its own: an `Agents\Agent` subclass in `.commandments/custom/`, naming
the folder that agent discovers skills in and the file it reads instructions from, turned on by name in the config.
The Go tool ships Claude Code and Codex as types of its own and reads no agent from a project, so the README no longer
offers one. Building agents from a project's data is a feature of its own, not taken with the switch. A class left in
`.commandments/custom/` is skipped and named like any other PHP class there, and an `agents` entry in `config.json`
that names neither `ClaudeAgent` nor `CodexAgent` is skipped, and `sync` says so.

What a project does instead:

- An agent that reads `AGENTS.md` or `.agents/skills/` — Codex, Cursor, Copilot and the rest of the `AGENTS.md`
  ecosystem — needs nothing: `sync` writes both for every project.
- An agent that looks elsewhere is pointed there by the project itself — a link from the folder it reads to
  `.agents/skills/`, an import of `AGENTS.md` in its own instructions file — or asked for with
  `commandments feature-request`, so the tool ships it as it ships the two.
- Then delete the class, and the name from `agents` in `config.json`.
