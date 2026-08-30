# The orchestrator, as a standalone system

This is the specification for `orchestrator-standalone`. It is Jesse's design; the reasoning
below is the orchestrator's, and where the two differ the design wins.

## The one idea

**The orchestrator stops being a feature of code-commandments and becomes its own house.**
Its own namespace, its own commands, its own state files, its own hooks — written in bash —
and its own instructions, in markdown, that nothing hardcodes. It runs in a session that
hears NONE of the project's hooks. It can read every file in the project; it simply is not
governed by the project's wiring.

Why standalone rather than another folder under `Cli/`: the orchestrator's hooks have to run
in a session that is deliberately cut off from the package's own hook suite. A hook that must
work while the package's wiring is absent cannot be a PHP class inside that wiring. So the
state it reads must be readable from bash — hence `.sentinel` files and json, not
`Cli\State\StateFile`.

> **This contradicts CLAUDE.md's "Session state is ONE format" rule, deliberately and only
> here.** Write that reason into the code, at the top of whatever owns the format, so the next
> reader does not "fix" it back.

## Five things it must be

### 1. One verb, dead simple

    commandments orchestrator <action> [...args]

Flat actions. No nested sub-verbs (`orchestrate plan add`, `orchestrate template use` — that
shape is what this replaces). An action is one word and it does one thing.

Start with exactly these, and add none you cannot justify:

| action | does |
|---|---|
| `setup [profile]` | start orchestration mode — see (3) |
| `status` | what is running, what is waiting on the orchestrator |
| `agent <name>` | stand up a sub-agent: its worktree, its world, its tooling, its brief |
| `instructions [name]` | print the instruction library, or one instruction |
| `stop` | leave orchestration mode |

### 2. Every instruction is a markdown file. NOTHING is hardcoded.

**No prompt text lives in PHP.** Not a brief, not a nudge, not a hook's message, not a role.
Every word the orchestrator is ever told lives in an `.md` file under the profile, and the
code's only job is to find it, fill its holes, and print it.

This package already works this way in one place — `src/Cli/Orchestration/Reminder.php`,
`Reminders.php`, `Holes.php` and the profile's `reminders/` folder ("every word a hook says is
an editable file in the profile"). **Read those first and follow that pattern**; do not invent
a second one.

Each instruction file must state, in its own words, two things:

- **WHAT to do** — exactly, as steps, not as principles;
- **WHEN to do it** — the moment that triggers it.

An instruction that says only what is a document. An instruction that says when is a rule.

### 3. `setup` is how orchestration mode starts

A human says *"please start orchestration mode"*. The agent runs:

    commandments orchestrator setup [profile]

A **profile** is reusable — it is nothing more than the default settings for an orchestrator,
so a second build starts from the first one's decisions.

- **Profile does not exist** → generate the whole tree: settings, the instruction library, the
  roles, the hooks. Generated files carry real, detailed content — not headings with TODOs. A
  scaffold nobody can act on is why the last one went unused.
- **Profile exists** → use it. Never overwrite a file a human may have edited.
- Either way, `setup` ends by printing the orchestrator's brief: who it now is, what it may
  never do, and the first thing to do next. That printed brief is itself an `.md` file.

### 4. The orchestrator's session is ISOLATED from the project's hooks

The orchestrator must hear its own hooks and **nothing else** — no stop hook holding it, no
journal gate, no judge nudge, none of the package's wiring.

**The mechanism already exists: `src/Cli/Orchestration/World.php`.** A world is a directory of
settings handed to an agent as `CLAUDE_CONFIG_DIR`; `commandments world <agent>` builds one,
and `lane open` already prints one per lane. It has two kinds today — worker and assistant —
both derived from which of the package's hooks are MARKED for that kind.

Add the third kind: **the orchestrator's world, which contains only the orchestrator's own
hooks and none of the package's.** Reuse `World`; do not write a second world builder.

`setup` produces that world and tells the human the one command that starts an isolated
orchestrator session with it, e.g.

    CLAUDE_CONFIG_DIR=<world> claude

Jesse offered a fallback if that is not enough: **a setup script that launches the orchestrator
already isolated**, so it is never un-isolated even for one turn. If binding the world after
the fact turns out to be leaky, write the script and say so in your report.

### 5. The orchestrator's hooks are BASH

Rewritten in bash, in the profile, standalone from the PHP package. They read `.sentinel`
files and a json config. A `.sentinel` file's presence, or its single value, IS the state —
that is what makes it readable from a two-line shell script.

Keep them few and keep them obvious. A hook whose job cannot be explained in one line does not
belong in a system whose whole point is that nothing interferes with the orchestrator.

### 6. The orchestrator sets up its OWN sub-agents, in a worktree

    commandments orchestrator agent <name>

The twin of `setup`: that one stands up the orchestrator, this one stands up a worker. It
does the whole thing in one act — nobody should have to remember four commands and a `cd`:

- a **worktree** of its own for that agent to work in (this is `lane open` today —
  `src/Cli/Orchestration/LaneCommand.php` and `Checkout.php`; reuse them, do not write a
  second worktree opener);
- the project's own setup run inside it, so the lane is REAL — a worktree checks out tracked
  files and nothing else, and a lane missing its `vendor/` does not fail loudly, it runs its
  gates against nothing and reports green;
- its **isolated world** (§4), carrying that agent's hooks and no others;
- its **tooling** — whatever we hand a worker that the bare harness does not have;
- its **brief**, from the instruction library (§2) — never a string in PHP;
- and it prints the exact command that starts that agent, ready to paste.

Jesse spelled this `orchestrate agent setup [name]`. Take the canonical form as
`orchestrator agent <name>` and accept `setup` as an optional noise word between them — every
action here is a setup, so the word carries nothing, and refusing a spelling somebody was told
to type is a worse failure than accepting both.

## Addendum — "running commands is basically all it has to do" (Jesse, mid-flight)

Verbatim design constraint added while this was being built:

> "SO ALL TOOLS WILL BE PROVIDED FOR THE ORCHESTRATOR TO MANAGE AND STEER AGENTS ETC! SO
> RUNNING COMMANDS IS BASICALLY ALL IT HAS TO DO"

**The orchestrator's entire job is running commands.** It does not read code, does not read
diffs, does not write files, does not hand-roll a `git worktree` line or a `claude --print`
invocation. If steering a build requires an act, there must be an ACTION for it — and if there
is no action, that is a gap in THIS item, not something the orchestrator works around with a
shell one-liner.

Working through "what does an orchestrator actually have to DO" against what already exists:

| need | action |
|---|---|
| stand one up | `orchestrator agent <name>` (new, §6) |
| tell it something / send it back | `commandments build rework <item> --because="…"` (existing) |
| replace it | `commandments build orphan <item>` then `build claim <item> --by=<new>` (existing) |
| take work off it | `commandments build release <item> --reason="…"` (existing) |
| see what is running | `commandments build` (existing — shows `working (n of limit)`) |
| see what is waiting on me | `commandments build` (existing — leads with `waiting on you (n)`) |
| settle a piece of work | `commandments build accept <item>` (existing) |
| end the build | **missing — built here**: `commandments build end` |

So most of the surface an orchestrator needs to STEER a build already exists under `build`,
`lane` and `world` — those are commands too, and `commandments orchestrator` does not
re-wrap them under its own verb. Duplicating `build claim` as `orchestrator claim` would be a
second, thinner command over the same `Board`, which is exactly the "second mechanism" CLAUDE.md
forbids. Instead `orchestrator status` (§ actions) prints the board summary itself (composing
`Board` directly, the same object `BuildCommand` composes) and its next-move line names
`commandments build` — REACHING the existing command by naming it, not reimplementing it.

**The one genuine gap: nothing ended a build.** `build accept`/`release` settle ONE item; nothing
cleared the board itself. Added `commandments build end` to `BuildCommand`/`Board` (both already
mine to touch — neither is `OrchestrateCommand.php` nor `Plan.php`): refuses while anything is
still `working` (settle or release it first), otherwise clears the board file via the same
`StateFile::delete()` `Instance::stop()` already uses, and says so. `orchestrator stop` (§1) is a
DIFFERENT act — it leaves orchestration mode (the session's profile-in-force), never the board —
and if the board still has unsettled work it says so and names `build end` rather than doing it
silently. Two honestly-different endings instead of one verb pretending to be both.

**Every action prints its next move.** Carried through everywhere written here: `setup` ends on
the paste-ready launch command, `agent` ends the same way, `status` ends on the command for
whatever it found, `instructions` with no name ends on how to read one, `build end`/`stop` each
end on what a doctor would still see. Nothing here should leave the orchestrator inferring what
its own tool just told it.

## Decisions this spec did not make, resolved while building

- **Namespace.** New, standalone: `JesseGall\CodeCommandments\Orchestrator\` under `src/Orchestrator/`.
  It depends on the existing `Cli\Orchestration\*` classes named in §6 (World, LaneCommand's
  worktree act, Checkout, Reminder/Reminders/Holes) but nothing in `Cli\Orchestration` depends
  back on it — the new house sits beside the old one, not inside it.
- **The instruction library is its own profile folder, `instructions/`** — sibling to the existing
  `roles/`, `procedures/`, `reminders/`. It is NOT the same as `reminders/`: those are keyed by
  the old trigger names and read through `OrchestrateCommand`'s bind/dispatch machinery, which is
  off limits this round. `instructions/` is read by the new `Orchestrator\Instructions` class,
  which is `Cli\Orchestration\Reminders` verbatim in shape (profile file wins, shipped file is the
  fallback, `Reminder::spoken` + `Holes` do the reading) — the SAME two classes are reused
  directly rather than re-derived, exactly as §2 asks. `Profile.php` and `Templates.php` gained
  the small, symmetric `instruction*`/`'instructions'` members `reminder*`/`'reminders'` already
  had — additive, nothing existing renamed or moved.
- **Bash hooks read their OWN words from an instruction file at fire time**, rather than a string
  baked into PHP or into the `.sh` at generation. `templates/hooks/*.sh` `cat`s its paired
  `templates/instructions/*.md` and strips only `#` heading lines and leading blanks (a few lines
  of `sed`, not the AST layer the "no regex for structure" rule governs — that rule is about
  detectors/scribes reading *code*, not a shell script reading its own prose). Edit the `.md`,
  the hook says something different, nothing regenerates.
- **Exactly two shipped orchestrator hooks**, each one line to explain:
  - `PreToolUse` — blocks `Edit`/`Write`/`NotebookEdit`. The orchestrator runs commands; it does
    not edit code itself (the addendum above, enforced rather than merely written down).
  - `Stop` — while a build is active (a `.sentinel` file `agent` touches on first use), shells out
    to `commandments build` itself and blocks-and-continues if anything reads "waiting on you".
    This is the orchestrator's OWN session calling its OWN CLI — not the disabled PHP hook-dispatch
    wiring (`HookRegistry`/`Discipline`), which is what §4 actually cuts off. Reading a `.sentinel`
    file's bare presence, and shelling out to the one CLI that is always on `$PATH` inside the
    world, is what keeps a hook readable in one line without a JSON library in `sh`.
- **`World` grows a `WorldKind` enum** (`Worker`, `Assistant`, `Orchestrator`) replacing the bare
  `bool $assistant` it took before — a third bool would have been unreadable at the call site, and
  the enum is itself the `enums-with-behaviour` rule applied to a decision the class already had.
  `forOrchestrator()` takes the resolved `Profile` too (the other two kinds need none — their hooks
  are the package's fixed built-ins; the orchestrator's are the profile's own files) and writes
  settings through a NEW `Orchestrator\HookSettings`, sibling to `Hooks\AgentSettings`, wiring each
  `<profile>/hooks/<Event>.sh` it finds by the event its filename names — never through
  `AgentSettings`, which wires `php … hooks` and the package's PHP `Discipline`s, i.e. exactly the
  wiring §4 says the orchestrator must not hear.
- **`agent <name>` reuses `LaneCommand`'s worktree act rather than re-driving `git worktree`.**
  `LaneCommand::open()` was extracted into a public `openInto(): OpenedLane` (a value object —
  path, world, exit code) that both `LaneCommand::run()` and `Orchestrator\OrchestratorCommand`
  call; the printed lines differ (the agent action adds its brief and the paste-ready command) but
  the act — worktree, then the profile's `lane.sh` — runs exactly once, in one place.
- **The world-binding fallback script (§4's "if this turns out to be leaky").** Written: every
  world `setup`/`agent` prepares also gets a `start.sh` (`CLAUDE_CONFIG_DIR=<world> exec claude
  "$@"`), and the printed brief offers both forms. Untested against the leak Jesse described
  (there was no live orchestrator session to try it against) — said plainly in the final report
  rather than claimed fixed.

## What you must NOT touch

`src/Cli/Orchestration/OrchestrateCommand.php` and `src/Cli/Orchestration/Plan.php` are
**held by another worker in another lane** — it is replacing the plan tree with a task system.
Touching either is a merge conflict, and the board exists to stop exactly that.

So: **move nothing and rename nothing this round.** Build the new house beside the old one.
Folding the existing `orchestrate` verbs in as `orchestrator` actions is a SECOND item, after
the task lane lands. Say in your report which of today's `orchestrate` forms should become
actions and which should die.

## The house rules (they are enforced here)

1. Read `CLAUDE.md` and `AGENTS.md` before you write anything.
2. Load the skills the work touches — at minimum `commandments-backend-fix-at-the-source`,
   `commandments-backend-documentation`, `commandments-backend-guard-clauses-and-flow`,
   `commandments-backend-value-objects`. Load them; do not work from memory.
3. Every `Cli\Command` declares `help(): Help` beside the code that parses its flags, and a
   wrong invocation goes through `HelpScreen::usage(...)` — never `fwrite(STDERR, "Usage: …")`.
   See "A command DOCUMENTS ITSELF" in CLAUDE.md.
4. Reuse the arsenal: `Workspace`, `Support\File::write`, `Support\Binary` (never a literal
   path to the binary), `Cli\Console`, `Cli\Input`, `Option` from php-types.
5. A structured thing gets a type, not an array.
6. Tests for the mechanism. Run **only** what you affect. **Do not run the full suite and do
   not run `judge`** — Jesse asked for neither, explicitly.
7. Commit in your lane. **No `Co-Authored-By`, no `Claude-Session`, no attribution trailer of
   any kind.** The message ends with its own last line.
8. `git stash` is FORBIDDEN — `refs/stash` is one ref shared by every worktree, and a pop has
   already taken another lane's work once. Copy the file aside instead.
9. Never merge into `main`. Never write outside your lane.
