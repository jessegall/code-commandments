# Code Commandments

Code Commandments checks the architecture of your code. It finds the places where the code breaks a design rule,
points at each one by file and line, and links the short lesson that explains the fix. It supports PHP, TypeScript,
Vue, Python, C# and Go.

It ships with a set of ready-made rules that check a codebase for common patterns and common architectural mistakes.
You can also write rules of your own, so the architecture your team agreed on is checked the same way.

## Installation

Code Commandments is one program for macOS and Linux. It reads each language with that language's own tools, so the
machine needs the toolchain of each language you want judged.

### PHP

Install it with Composer:

```bash
composer require --dev jessegall/code-commandments
vendor/bin/commandments install
```

Composer downloads the program for your machine. It needs PHP 8.3 or later. When your project has a
`vendor/autoload.php`, the classes of your dependencies are read too.

### TypeScript and Vue

Download the program for your machine from the
[latest release](https://github.com/jessegall/code-commandments/releases/latest) and put it on your `PATH` as
`commandments`. Judging TypeScript and Vue needs Node 18 or later.

### Python

Download the program for your machine from the
[latest release](https://github.com/jessegall/code-commandments/releases/latest) and put it on your `PATH` as
`commandments`. Judging Python needs Python 3.10 or later with `venv` and `pip`. The first
run builds a small environment with mypy, which needs the network once.

### C#

Download the program for your machine from the
[latest release](https://github.com/jessegall/code-commandments/releases/latest) and put it on your `PATH` as
`commandments`. Judging C# needs `curl` and the network once: the first run downloads
the C# reader from the release and checks it. It needs no .NET, but with a .NET SDK installed the framework's types
are read too.

### Go

Download the program for your machine from the
[latest release](https://github.com/jessegall/code-commandments/releases/latest) and put it on your `PATH` as
`commandments`. Judging Go needs the Go toolchain, so the standard library's types are
read.

### With the agent journal

If your project uses the [agent journal](https://github.com/jessegall/agent-journal), you can install Code
Commandments as a journal plugin instead:

```bash
journal plugin install https://github.com/jessegall/code-commandments
```

This downloads the same program and checks it. It needs `sh`, `curl`, `sha256sum` or `shasum`, and Python 3.9 or
newer. Journals from version 5 on read `journal-plugin.5.json`; older journals read `journal-plugin.json`. Both
install the same release.

When a language's toolchain is missing, that language is not judged and the run says so once; every other language
is judged as usual. [docs/requirements.md](docs/requirements.md) has the details.

## How it works

Run it on a project and you get a list of problems. Each one names a file and a line, says what is wrong, and points
to a short lesson (a "skill") that explains how to fix it. For example:

```
app/Orders/RefundService.php, line 42: a catch block that swallows the error [swallow-catch]
app/Checkout/PricingService.php, line 18: passing three pieces of one object instead of the object [derived-argument]
```

A linter tells you a line is too long. This tool tells you that an array should be a value object, or that a method
belongs on another class, and why.

It never changes your code. You fix each problem yourself, or your coding agent does, using the lesson the problem
points to.

1. **Reading.** Each language is read by its own compiler: php-parser for PHP, the TypeScript compiler for TypeScript
   and Vue, mypy for Python, Roslyn for C# and go/types for Go. A small program for each language, called a bridge,
   turns what the compiler read into one shared format: the same tree shape for every language.
2. **Judging.** Every rule is one JSON file that searches that shared tree. Because the tree looks the same for every
   language, a rule is written once and works for every language it lists.
3. **Teaching.** Every rule points to a skill: a short document that explains the design rule and shows the fix.

Code Commandments is one program. It remembers what it read and what each rule decided, keyed on the contents of each
file. A second run only reads and judges the files that changed, plus the files that depend on them. Every copy of a
project on your machine, including git worktrees, shares that memory.

## Usage

```bash
commandments judge                       # judge the folders listed in your config
commandments judge src                   # judge one folder
commandments judge --changes             # judge only what you changed and have not committed
commandments judge --branch              # judge only what differs from main
commandments judge --sin=swallow-catch   # look for one kind of problem
commandments judge --skill=exceptions    # look for every problem one skill covers
commandments info swallow-catch          # explain a problem: what it is, why, and how to fix it
```

Use `vendor/bin/commandments` when you installed with Composer. Under the journal, `journal sins` runs the same
judge and shows its progress.

The program exits with code 1 when it finds problems and 0 when the code is clean. Each run also writes a checklist
file. Work through it top to bottom: fix one problem, delete its line, and move on. Run judge again at the end to
confirm.

Every command explains itself with `--help`:

<!-- BEGIN: commands-table (auto-generated, run `composer readme`) -->
| Command | Purpose |
|---|---|
| `commandments judge [path]` | Scan a codebase and report its sins, grouped by the skill that fixes each. Exit code 1 when sins are found, 3 when a rule could not run, 4 when another run was already reading the project. |
| `commandments make <Name>` | Scaffold a commandment of your own — a rule naming its sin, and the skill that teaches the fix, in `.commandments/custom/`, turned on in your config, with the rest of the process printed for you. |
| `commandments scaffold` | Generate the reusable helper a sin's fix uses — written into your source root with its namespace injected. Idempotent: an existing file is skipped. |
| `commandments report --reason="…" --ref=PATH:LINE` | File a GitHub issue about code-commandments itself (via `gh`) — a false positive, a wrong rule, or a bug. |
| `commandments feature-request --title="…" --reason="…"` | File a [feature-request] GitHub issue (via `gh`) proposing a new or changed rule. |
| `commandments freeze <path>` | Mark a file intentionally immutable, or lift the mark. A frozen file is still scanned (so cross-file rules stay correct) but never flagged and never rewritten. |
| `commandments sync` | Refresh this project's code-commandments integration — publish the skills into the library every agent reads, refresh the AGENTS.md briefing and the config surface, and wire each agent's own view of them. |
| `commandments install` | Wire a consumer project up once — the composer sync hook, every agent it supports (skills, AGENTS.md, and under Claude Code the hook suite) and .gitignore — then sync. |
| `commandments judge-reminder` | A "did you judge?" nudge wired to `Stop` and `PreToolUse` hooks; reminds when judged files are touched but unchecked, deduped per changed-file set. |
| `commandments session` | Where this session keeps its state — the folder holding its checklist and hook counters. |
| `commandments task` | The work in front of this session — numbered tasks, one markdown file each, moved between queue, active and history. |
| `commandments hooks` | The wired hook entry point — reads one hook payload from stdin, runs every registered handler, and merges their responses into one. |
| `commandments plugin-hook` | The agent journal plugin's program — reads one event line from stdin and answers it at once: an edit, a command and a stop with nothing, and every other event with what its handlers found. |
| `commandments dashboard-refresh` | Refresh the journal dashboard from saved findings. |
| `commandments php-serve` | Keep the PHP bridge running for this project, answering each run of the tool over a socket named for the project. |
| `commandments frontend-serve` | Keep the Vue and TypeScript bridge running for this project, answering each run of the tool over a socket named for the project. |
| `commandments roslyn-serve` | Keep the C# bridge running for this project, answering each run of the tool over a socket named for the project. |
| `commandments mypy-serve` | Keep the Python bridge running for this project, answering each run of the tool over a socket named for the project. |
| `commandments has-php` | Say whether this project has PHP to judge: exit 0 when it has, 1 when it has none. |
| `commandments has-frontend` | Say whether this project has Vue and TypeScript to judge: exit 0 when it has, 1 when it has none. |
| `commandments has-csharp` | Say whether this project has C# to judge: exit 0 when it has, 1 when it has none. |
| `commandments has-python` | Say whether this project has Python to judge: exit 0 when it has, 1 when it has none. |
| `commandments has-go` | Say whether this project has Go to judge: exit 0 when it has, 1 when it has none. |
| `commandments lanes` | Say what the last merges and hand-ins brought in, as the project keeps it. |
| `commandments why <file>` | Say what the last judging of a file was: the checkout it was judged in, whether a tree was held, which rules were asked, and what was found. |
| `commandments journal-config` | Write the agent journal plugin's chosen switches into .commandments/config.json, and refresh what the project is told. |
| `commandments journal-scan` | Scan the project for the folders to check and the ones to leave out, for the agent journal plugin. |
| `commandments journal-skills` | Render the skills into the agent journal plugin's folder, for the journal to publish. |
| `commandments hook <Name>` | Run ONE hook directly, by name — the form every wired hook is written as. |
| `commandments disable <sin\|skill>` | Toggle a rule in the project's .commandments/config.json, leaving the rest of the file as you wrote it. |
| `commandments allow lone-implementer-interface <path-or-symbol>` | Publish a public interface for the lone-implementer rule. |
| `commandments config` | Inspect and manage .commandments/config.json — what is configured, and what is actually running. |
| `commandments layers [path]` | Read the dependency stack this project ALREADY has and propose the layer declaration for it — the rule is inert until one is declared, and nobody writes that from a blank file. |
| `commandments rule explain <file> [--line=N]` | Work on a rule of your own: see the tree it reads, try it on a path, prove it on the samples that mark it, and print the schema an editor checks it by. |
| `commandments exemptions` | List the exemption tags — what a package registers to quiet a general rule on its own boundary types. |
| `commandments info <sin\|detector>` | Explain one sin — what it flags, why it is a sin, how to fix it, and a worked example. |
| `commandments trigger-eval` | Measure whether skill descriptions pull their own skill in — and stay out of their neighbours'. |
<!-- END: commands-table -->

## Configuration

You do not have to configure anything: every rule is on. The settings live in `.commandments/config.json`, created
when you install, with a schema beside it, so your editor can complete every name.

```json
{
    "$schema": "./config.schema.json",
    "paths": ["app", "src"],
    "exclude": ["app/Generated", "**/vendor"],
    "disable": {
        "sins": ["php/swallow-catch"],
        "skills": ["php/Exceptions"],
        "languages": ["python"]
    },
    "parallel": 2,
    "base": "main"
}
```

- `paths`: the folders judge reads when you give it no folder.
- `exclude`: folders and files judge never reads.
- `disable`: rules to switch off, by problem, by skill, or a whole language.
- `parallel`: how many rules run at once.
- `base`: the branch `--branch` compares against.

`commandments disable <name>` and `commandments enable <name>` edit the file for you. `commandments config` shows
what is in effect. A name in the file that no rule knows stops the run with a message that names every line to fix.

### Layers

One rule needs to know your design first: which part of the code may use which. Declare your layers from the top
down, and the rule reports every reference that points the wrong way. `commandments layers` reads the layers your
code already has and prints the declaration, and `commandments layers --write` adds it to your config, so everything
that passes today keeps passing.

### Frozen files

Some files carry problems on purpose, such as generated code or a frozen migration. `commandments freeze <file>`
marks one. Judge still reads a frozen file, so the files around it are judged correctly, but it never reports a
problem in it.

## Agent journal

The plugin connects Code Commandments to the journal's events. It needs no service running in the background.

- **Judging.** `journal sins` runs judge for an agent, and the journal queues it with the agents' other runs. A
  `commandments judge` you type in a terminal runs right there and never waits in that queue. Both read through
  the same cache.
- **Live judging.** When it is on, every file an agent edits is judged, and the agent is told which problems it just
  added and which it fixed.
- **Settings.** The journal's plugin page has a switch for every rule and every language. A switch writes the same
  `disable` entry you would write by hand.

## Coding agents

The skills are plain documents, written once into your project's `.agents/skills/` folder. Every coding agent reads
them from there:

<!-- BEGIN: agents-table (auto-generated, run `composer readme`) -->
| Agent | Skills | Instructions | Enforced by hooks | What it gets |
|---|---|---|---|---|
| **Claude Code** | `.claude/skills` _(links)_ | `CLAUDE.md` | yes | skills, `CLAUDE.md` (imports `AGENTS.md`), and the hooks — the only agent whose disciplines are enforced rather than only written down |
| **Codex** | `.agents/skills` _(read directly)_ | `AGENTS.md` | no | skills and `AGENTS.md`, both read where they already live — no links, no hooks |
<!-- END: agents-table -->

`AGENTS.md` holds the instructions every agent reads. `CLAUDE.md` imports it and adds what is true only for Claude
Code. Both are edited in place: only the block between their markers is ever written, and the rest of the file stays
yours.

Claude Code can run commands on its events, so it is the one agent where the rules are checked as it works, not only
written down. Composer's install wires these hooks into `.claude/settings.json`. Every hook it wires is stamped, and
only stamped hooks are ever changed, so your own hooks are safe. Under the agent journal, the journal runs them
instead.

<!-- BEGIN: hooks-table (auto-generated, run `composer readme`) -->
| Hook | Events | What it does |
|---|---|---|
| `JudgeReminder` | `Stop, PreToolUse/Bash, PostToolUse/Bash, PostToolUse/Edit, PostToolUse/Write, PostToolUse/MultiEdit` | Nudges you to `judge` what you changed — before a risky Bash command, and on stop. |
| `ModelChoiceReminder` | `PreToolUse/Agent` | Asks for an explicit model when an agent is dispatched without one, since an unnamed model inherits the dispatcher's. |
| `SessionReset` | `SessionStart` | On a fresh session (startup/clear) wipes lingering hook counters and prunes stale session folders. |
| `SourceReminder` | `PreToolUse/Edit, PreToolUse/Write, PreToolUse/MultiEdit` | When you edit a test/stub/fixture (which `judge` never scans), nudges you to check the real fix belongs at the SOURCE. |
| `SkillReminder` | `PostToolUse/Edit, PostToolUse/Write, PostToolUse/MultiEdit, PostToolUse/Bash` | After an edit — including one made with the shell — checks the files against the rules that can judge one file and names the skill that teaches the fix. |
<!-- END: hooks-table -->

## Writing your own rules

A rule of your own is a JSON file in `.commandments/custom/`, in the same format as every rule this package ships
(`commandments:neutral-rule/v2`). It names the languages it judges, the problem it finds, the skill that teaches the
fix, and a search over the shared tree:

```json
{
    "$schema": "commandments:neutral-rule/v2",
    "engine": ["php", "python", "go"],
    "sin": {
        "name": "too-many-parameters",
        "description": "A function declares five parameters or more.",
        "rule": "Give the values that travel together a type of their own, and pass that.",
        "skill": "small-signatures"
    },
    "find": {
        "select": "function",
        "as": "f",
        "where": [">=", ["count", "$f.parameters"], 5],
        "emit": "$f"
    }
}
```

`commandments make <Name>` creates a rule and its skill to start from. `commandments rule explain <file> --line=N`
shows the tree a rule reads, `commandments rule try <rule> <folder>` runs one rule, and `commandments rule prove`
checks it against your own examples. The `commandments-writing-detectors` skill explains the whole format. A rule
file in an older format is refused, and the message names the file.

## Skills and rules

<!-- BEGIN: skills (auto-generated, run `composer readme`) -->
_75 skills. Full table in [README.skills.md](README.skills.md)._
<!-- END: skills -->

Every problem the tool finds, and the rule file that finds it:

<!-- BEGIN: detectors (auto-generated, run `composer readme`) -->
_175 sins across 40 skills. Full tables in [README.sins.md](README.sins.md)._
<!-- END: detectors -->

## License

MIT.
