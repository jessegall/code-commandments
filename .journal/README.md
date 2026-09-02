# `.journal/`

A session survives its own compaction.

A compaction keeps what was **done** and loses what was **decided**. The transcript on
disk lost nothing. This is the index that gets you back to it.

## It writes almost nothing

`journal` is a **read** tool over the session's own `.jsonl`. There is no store to keep
current, no command to remember, and nothing that is true only if somebody was
disciplined. Filing is a side effect of speaking.

Two things are written, and both are deliberate acts rather than records of what happened:
**pins** (a fact that must cross a compaction) and **open work** (a commitment). Everything
else is derived on demand.

## Shared, and not shared

The **record** — rules, pins, work, tracks — belongs to the project. Every session and every agent
inside one reads and writes the same `record.json`, it is committed, and a session that
starts tomorrow is handed the store exactly as today's was. That is the point: the journal
is not a session's memory, it is the project's.

The hook's own **marks** are not shared. Where the untagged hold last fired, which context
rung was announced, the largest tool result so far: each is a line number or a reading of
ONE transcript, and it lives in `runtime/<transcript>.json`, gitignored. When these were
project-wide, a session at line 53 inherited a hold mark of 1746 from the session before it
and could not be held until line 1747; a subagent's read silenced its parent. Nothing about
either looked broken.

**Subagents are out, except for the rules.** Their tool calls are not gated or nudged,
their attempts to write the record are denied, and they are handed the rules on their
first tool call and again at 25%, 50% and 75% of their own window. They report; the main
conversation files.

## Tag what you say

Open the message with one. It rides on a message you were sending anyway, so it costs
nothing — use one every time.

| tag | for |
|---|---|
| `[!discovery]` | the real shape of something you did not know |
| `[!correction]` | something you had wrong is now right |
| `[!update]` | where the work stands, what you decided since the last one and WHY |
| `[!blocked]` | blocked, and on what |
| `[!reply]` | routine — answering, narrating, acknowledging |

`[!reply]` is what makes the rule enforceable: every message carries a tag, so a routine
one needs something honest to wear. Without it, "tag everything" is a rule you must break.

**A tag opens the MESSAGE.** Mentioning `[!discovery]` mid-sentence is talking about it,
not using it — otherwise a page documenting the tags would file itself under all of them.

## Declare work

    journal start "converting the drilldown"
    journal end   "converting the drilldown"

A tag is free; a declaration is not, and the difference is the point. Starting work is a
commitment, so it costs a command — and that cost is the thought.

## Pin what must not be lost

    journal remember "the user ruled motion.ts FORBIDDEN — the CSS transition replaces it"

Only pins reach the summariser's own instructions. **It never evicts:** at the cap the next
pin is refused and told which to strike. A pin that did not need to be there would delete
one that did.

Pin it only if: somebody **decided** it, the next reader would get it **wrong** without it,
and it will still be **true tomorrow**. A status, a count, or what you just did fails the
third and becomes a confident falsehood wearing the same authority as the facts that hold.

## What a session is handed

Every start — fresh, resumed, cleared, forked, or after a compaction — delivers the rules,
the standing pins, and the open work. Only a compaction adds the paragraph about the
summary you are holding, because only then is there one.

## The context ladder is a gate

The Stop hook warns at 50%, 70%, 90% and 95% of the window, once each. After each rung
**no other tool call runs** until one of these has:

    journal remember "<the claim>"
    journal nothing "<why nothing here needs pinning>"

It forces a decision, not a pin. The warning alone was measured and did not land, and a
gate that manufactured pins would be the padding the warning itself argues against, so
`nothing` with a reason is exactly as good a way through. The journal's own commands stay
available to decide with.

## A rule is a pin for every track

    journal rule "<the ruling, in one line>"
    journal rules
    journal promote <n>

A pin is what one line of work decided; a rule is what the project decided, and switching
tracks never moves it. It is handed to every session first. Same cap, same citation into
the transcript, one more question before writing one: would it be wrong on any other
track? `promote` lifts a pin into a rule and strikes the pin, so a claim never stands in
two places.

## Delayed work

    journal todo "<title>"      one titled file under todo/<track>/; --brief reads a brief from stdin
    journal todo                the titles
    journal todo start <n>      picks one up as open work; `end` closes both

A to-do is work that was put off, scoped to the track that deferred it. The start block
lists what is waiting and an idle stop says so once; neither is an instruction to start
one.

## After a compaction, before you touch anything

    journal --back=1     the stretch the last summary REPLACED
    journal user         the user's own words, in full
    journal open         work you declared and never closed

`--back=1` is precisely what the summary dropped.

The CLI reads **this session's** transcript, from `CLAUDE_CODE_SESSION_ID` in the
environment of every Bash call. At a bare terminal it falls back to the newest transcript
and says so, because with two terminals open the newest is the other one.

## Is any of it in force?

    journal verify

Reports **wired** and **fired** separately, because they are different facts. A hook that
is configured and silent is the one failure nobody can point at — in the tool this
replaces, that shape ran for seventeen hours and captured nothing. Fired is itself two
facts: in some transcript on this machine, and in **this** session.

It also says when `context_window` is unset. The context ladder is climbed against a
window, and it is never guessed: inferring the smallest window that fitted reported 54% at
108k tokens of a 1M window and burned every rung before 20%. Unset, the ladder is silent
until the session's own peak has ruled out every window but one.

## Settings

`.journal/settings.json` is a record of your **disagreements** with the defaults; anything
not in it is at default. An unknown key is reported, never ignored. `journal settings`
lists them all.

## install

Drop `.journal/` into a project and run:

    .journal/install.py --alias

It merges its four hooks into `.claude/settings.json` rather than writing over it —
whatever else you have wired on `Stop`, `PreCompact` or `SessionStart` is kept, and running
it twice adds nothing. If that file does not parse it stops and says so instead of starting
from `{}`, because an unreadable config is not an empty one.

It draws no line under history; the hook does that itself. The first event that sees a
transcript writes a floor at that line, so a project with a year of untagged history, a
resumed or forked session, or a hook wired mid-session is never held for what was said
before the journal was present.

It also installs the `journal` skill into `.claude/skills/`, and re-installs it whenever
the packaged copy changes. The skill is package output, not a place to keep notes: edit
`.journal/skill/SKILL.md` and the next install carries it everywhere.

`--alias` adds a `journal` alias to your shell rc. `--check` says what would change and
writes nothing.

To bring a consumer up to the package's latest, from inside the consumer:

    .journal/install.py --from ../orchestrator/.journal

The code, tests and skill come across; the record, settings and runtime files stay. The
pulled copy runs its own three suites in a staging directory first, and a package that
fails them is refused before a single file lands.

It finishes by running `verify`, which reports WIRED and FIRED as separate facts. Installing
can only prove the first: nothing is in force until a hook has actually run.
