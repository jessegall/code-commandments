# Parity: the Go tool against the PHP tool

The Go tool replaces the PHP tool only if it judges a codebase the way the PHP tool does. This page records that
check on real codebases, one per language the tool reads, each pinned to a commit: how many findings each tool
makes, every finding only one of them makes, and why.

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
