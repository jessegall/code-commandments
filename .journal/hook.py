#!/usr/bin/env python3
"""One doorbell. The payload says which event fired, so the harness config never changes.

    "command": "\"$CLAUDE_PROJECT_DIR\"/.journal/hook.py"

THE CONTRACT, and it is the whole reason this file is small:
  exit 0            silent; stdout is shown to the user
  exit 2 + stderr   the turn is HELD and stderr is fed back to the agent

The hold is what makes a rule a mechanism instead of a wish. It sits at the STOP and
nowhere else: a tool count is an arbitrary boundary that can fire mid-thought, while a
stop is the moment the stretch is about to be lost — which is the moment worth holding.

AND IT CAN ONLY HOLD ONCE PER STRETCH. A hook that re-holds on the message it provoked is
a loop the agent cannot leave, so the line it last held at is written down and it never
holds at or behind that mark again. A nudge that cannot be escaped stops being a nudge.

THE TRANSCRIPT COMES FROM THE PAYLOAD. Every event carries `session_id` and
`transcript_path`; the first version guessed the newest file by mtime instead, and with two
terminals open on one project it held session A for session B's messages. Every mark this
file writes is a fact about the transcript it was handed, and is filed under its name.
"""
from __future__ import annotations

import json
import re
import sys
from dataclasses import dataclass
from pathlib import Path

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))

import settings as settings_mod  # noqa: E402
import state  # noqa: E402
import tags  # noqa: E402
import context  # noqa: E402
import pins  # noqa: E402
import work  # noqa: E402
import todo  # noqa: E402
import tracks  # noqa: E402
import transcript  # noqa: E402


@dataclass(frozen=True)
class Ctx:
    """Which transcript this event is about, and where its marks go.

    `stem` names the runtime file. For the session's own events it is the transcript's
    stem. A SUBAGENT's tool call carries the PARENT's transcript and session — measured —
    and only `agent_id` tells them apart; it is keyed `agent-<id>`, the name of its own
    transcript on disk, so that nothing it does can land in the parent's file. In practice
    the handlers ignore subagents altogether (see `_subagent`), so no such file is written.
    """

    stem: str
    path: Path | None


def _ctx(payload: dict) -> Ctx | None:
    tp = payload.get("transcript_path") or ""
    sid = payload.get("session_id") or ""
    aid = payload.get("agent_id") or ""
    if aid:
        stem = f"agent-{aid}"
    elif tp:
        stem = Path(tp).stem
    elif sid:
        stem = sid
    else:
        return None
    path = Path(tp) if tp else transcript.find(ROOT.parent, sid) if sid else None
    return Ctx(stem, path)


# `filing_units` USED TO BE DEFINED HERE and now lives in `transcript.py`, because the
# digest needed the same answer. Two copies of "what counts as a message" is two rules, and
# they had already drifted: the hook stopped holding scaffolding while the digest went on
# printing it.


def untagged(lines, units: set[int]) -> list:
    """Messages that said nothing about what they carried.

    A `[!reply]` is NOT untagged — it obeyed the rule and declared itself routine. Only a
    message wearing no tag at all filed nothing, and only a FILING UNIT can be one.
    """
    return [
        l
        for l in lines
        if l.n in units
        and l.kind == "text"
        and (l.text or "").strip()
        and not tags.found(l.text)
        # A QUESTION PUT TO THE USER IS NOT A MESSAGE THAT NEEDS A TAG. It is spoken text
        # so the digest shows what the user answered, and the moment it was, the hold
        # judged it: "line 3014: asked: When a state key that a slot's factory read…".
        and not any(t in transcript.ASKS for t in l.tools)
    ]


def _floor(ctx: Ctx, lines=None) -> int:
    """The line under which nothing is held against anyone, written on FIRST SIGHT.

    A transcript the hook was not present for has a history, all of it untagged because
    there was no vocabulary to tag it with. That history exists for a fresh install into a
    running session, for a resumed or forked session whose transcript was copied at line N,
    and for a SessionStart hook that failed once in a session that then ran for hours. The
    first version drew this line only at install, into a transcript guessed by mtime; the
    second only at SessionStart, which does not fire when a hook is picked up mid-session.

    So WHICHEVER HANDLER FIRST SEES A TRANSCRIPT with no floor writes one, at the line count
    of that moment. In a fresh session that is SessionStart at line one or two and nothing
    is suppressed; in a session joined late it is the line it was joined at.
    """
    got = state.get(ROOT, "floor", None, stem=ctx.stem)
    if got is not None:
        return got
    if lines is None:
        lines = transcript.read(ctx.path)[0] if ctx.path else []
    floor = lines[-1].n if lines else 0
    state.put(ROOT, "floor", floor, stem=ctx.stem)
    return floor


def on_stop(conf: dict, payload: dict, ctx: Ctx) -> int:
    if not conf["hold_stop_on_untagged"] or "untagged" in conf["silenced"]:
        return 0
    if ctx.path is None:
        return 0
    lines, boundaries = transcript.read(ctx.path)
    stretch = transcript.since(lines, boundaries, 0)
    floor = _floor(ctx, lines)
    # CONTEXT PRESSURE FIRST — it outranks both other holds, because it is the only one
    # with a deadline. The others can be said at the next stop; this one cannot.
    # ONE RUNG, ONCE. The highest rung already passed is written down, so a session that
    # sits at 71% for an hour is told once and not once per stop — a warning that repeats
    # while nothing has changed is one the reader learns to clear without looking.
    # AND NEVER ON A GUESSED WINDOW. See `context.window_for`: a rung climbed against the
    # wrong window is a wrong nudge, and four of them leave the ladder mute for the real
    # compaction.
    ladder = sorted(conf["context_warn_ladder"])
    if ladder and "context" not in conf["silenced"]:
        got = context.pressure(ctx.path, conf["context_window"])
        if got and got[3]:
            done = state.get(ROOT, "warned_at", 0.0, stem=ctx.stem)
            passed = [r for r in ladder if got[0] >= r > done]
            if passed:
                rung = passed[-1]
                state.put(ROOT, "warned_at", rung, stem=ctx.stem)
                standing = pins.live(ROOT)
                # How many were written since the last rung — a fact, and the one that
                # would expose padding to the reader who is doing it.
                seen = state.get(ROOT, "pins_at_warn", 0, stem=ctx.stem)
                state.put(ROOT, "pins_at_warn", len(standing), stem=ctx.stem)
                gated = bool(conf["gate_after_context_rung"]) and "pin_due" not in conf["silenced"]
                if gated:
                    # RECORDED HERE, ENFORCED AT THE NEXT TOOL CALL. A Stop can only hold;
                    # PreToolUse is the one event that can refuse an act.
                    state.put(ROOT, "pin_due", {"rung": rung, "used": got[1],
                                                "window": got[2]}, stem=ctx.stem)
                pct = 100 * got[1] / got[2]
                text = context.warning(
                    got[1], got[2], len(standing), context.shape(stretch), rung,
                    latest=standing[-1]["fact"] if standing else "",
                    since=max(0, len(standing) - seen), gated=gated,
                )
                # THE RULES RIDE EVERY RUNG. A rule read at the session's start is far
                # behind by the time the window is half full, and it is a few lines.
                ruled = pins.carry(ROOT, "compact", key=pins.RULES)
                if ruled:
                    text += "\n\n" + ruled.replace(
                        "Decided, and still in force:",
                        "Again, because the block you read at the start is far behind you:")
                return _hold(
                    f"journal: context {pct:.0f}% full — "
                    + ("decide before any other tool runs: `remember \"<claim>\"` or "
                       "`nothing \"<why>\"`" if gated else "consider what must outlive it"),
                    text,
                )

    missing = untagged(stretch, transcript.filing_units(lines))

    # THE MISFILED CHECK USED TO LIVE HERE, and it is gone because the thing it policed
    # is gone. It held on a message wearing `[!update]` with no work open — the one tag
    # whose correctness depended on something outside the message it rode on. The user
    # struck the tag and made it `journal update` instead, and a command cannot be
    # misfiled: `work.note` refuses when nothing is open rather than filing a claim about
    # nothing. The check moved from after the fact to before it, which is where a check
    # belongs when it can.
    if not missing:
        # Open work is the other half of the same question: is the stretch safe to lose?
        # ONLY WORK THIS TRANSCRIPT OPENED. The journal is shared, so work opened in another
        # session is still open here — and this session was TOLD so at its start. Holding
        # it again at the first stop is the same nudge said twice, about a commitment
        # somebody else made and this reader cannot act on. A hold is for one's own.
        # ONCE PER PIECE OF WORK. Work legitimately spans a stop — that is what declaring
        # it is FOR — so a hold that repeats until it closes is a trap, not a reminder.
        mine = ctx.path.name if ctx.path else None
        held = set(state.get(ROOT, "held_work", [], stem=ctx.stem))
        fresh = [w for w in work.open_work(ROOT)
                 if w.get("session") == mine and w["subject"] not in held]
        if fresh:
            state.put(ROOT, "held_work", sorted(held | {w["subject"] for w in fresh}),
                      stem=ctx.stem)
            return _hold(
                f"journal: {len(fresh)} piece(s) of work still open — `end` it, or `update` "
                "where it got to",
                "Still open:\n"
                + "\n".join(f"  {w['subject']}" for w in fresh)
                + "\n\nClose with `journal end \"<the same words>\"` if it is done, or say "
                "where it got to\nso a reader on the far side of a compaction is not left "
                "with a start and no end.",
            )
        # NOTHING IS OPEN, AND SOMETHING IS WAITING. Said, never held, and only when the
        # list differs from the last time it was said in this transcript — a reminder at
        # every idle stop is wallpaper within the hour. And it is not permission: an idle
        # agent told "three are waiting" will start one, and whether it should is the
        # user's call.
        if not work.open_work(ROOT):
            here = tracks.current(ROOT)
            waiting = todo.open_items(ROOT, here)
            ids = sorted(t["n"] for t in waiting)
            if ids and ids != state.get(ROOT, "todos_said", [], stem=ctx.stem):
                state.put(ROOT, "todos_said", ids, stem=ctx.stem)
                return _context(
                    "Stop",
                    f"journal: {len(ids)} to-do(s) waiting on track `{here}` (`journal todo`). "
                    "Delayed work, not an instruction to start any of it — the user decides.",
                )
        return 0

    # THE HOLD FLOOR IS THE HIGHER OF TWO MARKS. `held_at` is where a hook last held
    # somebody; `floor` is where it first saw this transcript. Both are this transcript's.
    held_at = max(state.get(ROOT, "held_at", 0, stem=ctx.stem), floor)
    newest = missing[-1].n
    if newest <= held_at:
        return 0  # already held for these; do not trap the turn
    # Only what is NEW since the last hold. Re-listing lines already raised trains the
    # reader to skim the block, and the one line that matters is then skimmed with it.
    fresh = [m for m in missing if m.n > held_at]
    state.put(ROOT, "held_at", newest, stem=ctx.stem)

    # THE VOCABULARY IS TAUGHT ONCE. Measured by being on the receiving end of this hook:
    # the full list is worth reading the first time and is noise every time after, and a
    # block that is skimmed teaches the reader to skim the next one — which is how an
    # interrupt becomes ambience. So the reminder tapers to the size of the offence.
    taught = state.get(ROOT, "taught_vocabulary", False, stem=ctx.stem)
    shown = fresh[-3:]
    lines_out = [
        f"{len(fresh)} message(s) since the last nudge carried no tag, so the journal "
        f"filed nothing for them:"
    ]
    lines_out += [f"  line {m.n}: {' '.join((m.text or '').split())[:90]}…" for m in shown]
    if not taught:
        state.put(ROOT, "taught_vocabulary", True, stem=ctx.stem)
        lines_out.append(
            "\nA compaction takes exactly what was not filed. Open your next message with "
            "one of:\n"
            + "\n".join(f"  [!{t.name}]  {t.line}" for t in tags.TAGS.values())
            + "\n\nThe tag rides on a message you were sending anyway — there is no command "
            "to run."
        )
    else:
        lines_out.append(
            "\nTag the next one: "
            + " ".join(f"[!{t}]" for t in tags.TAGS)
        )
    lines_out.append("This will not hold you again for these lines.")
    return _hold(
        f"journal: {len(fresh)} message(s) carried no tag — open the next one with "
        + " ".join(f"[!{t}]" for t in tags.TAGS),
        "\n".join(lines_out),
    )


#: Tools whose ENTIRE PURPOSE is to change a file. No judgement needed for these.
WRITE_TOOLS = frozenset({"Edit", "Write", "NotebookEdit", "MultiEdit", "Update"})

#: Commands whose job is to change something. Matched as the COMMAND, never as text.
WRITE_CMDS = frozenset({
    "rm", "rmdir", "mv", "cp", "mkdir", "touch", "chmod", "chown", "truncate", "dd",
    "tee", "install", "patch", "ln", "unlink", "rsync",
})

#: `git` is only a write in some of its moods.
WRITE_GIT = frozenset({"commit", "apply", "checkout", "reset", "restore", "rm", "mv", "add"})

#: Where one command ends and the next begins. A write anywhere in a chain is a write.
_SPLIT = re.compile(r"[;&|]+|\n")
#: Quoted text is DATA, not a command, and it must be removed before anything is matched.
_QUOTED = re.compile(r"'[^']*'|\"[^\"]*\"")
#: A HEREDOC BODY IS DATA TOO, and it is the biggest body of text a command ever carries.
#: In auto mode nearly every analysis runs as `python3 - <<'PY' … PY`, and a script that
#: says `if shown >= 6` was being read as a shell redirection and denied as a write. The
#: opening `<<WORD` is kept, so `cat > file <<EOF` is still a write on the strength of its
#: own `>` — what is dropped is only what the interpreter, not the shell, will read.
_HEREDOC = re.compile(r"<<-?\s*'?\"?([A-Za-z_][A-Za-z0-9_]*)'?\"?.*?(?:\1|$)", re.S)


def _is_write(payload: dict) -> bool:
    """Is this tool call about to change something on disk?

    MATCHED AS A COMMAND, NEVER AS TEXT. The first version tested substrings — `"patch "`
    in the command line — and denied this, which is a pure read:

        cat resources/js/view/triggers.ts; echo "=== useDispatch ==="; cat …

    `useDis` + `patch ` matched inside a heading being echoed. The agent was reading, and
    it was made to declare work before it had learned enough to say what the work was.
    That is the worst possible failure for a gate: it fires on discovery, which is exactly
    when nobody can yet name the thing they are about to do, so it teaches that the gate is
    an obstacle to get around rather than a prompt to answer. Word boundaries are not a
    detail here; they are the difference between a prompt and a nuisance.

    So: quoted text is stripped first — it is data, not a command — the line is split on
    the separators that end a command, and only the FIRST WORD of each piece is judged.
    """
    name = payload.get("tool_name") or ""
    if name in WRITE_TOOLS:
        return True
    if name != "Bash":
        return False
    cmd = " ".join(str((payload.get("tool_input") or {}).get("command", "")).split())
    bare = _QUOTED.sub(" ", _HEREDOC.sub(" <<HEREDOC ", cmd))
    # A redirection into anything but the bin. Checked on the UNQUOTED text, so an echoed
    # `">"` is not a write. `2>/dev/null` and `>/dev/null` discard output and change nothing.
    for i, ch in enumerate(bare):
        if ch != ">":
            continue
        rest = bare[i:].lstrip("> ")
        # `2>&1` and `>&2` move a file descriptor; they create nothing. `>/dev/null` throws
        # output away. Both appear in ordinary reading — `2>&1` was the third false positive
        # this gate produced in a day, and every one of them stopped a read.
        if rest.startswith("&") or rest.startswith("/dev/null"):
            continue
        return True
    for piece in _SPLIT.split(bare):
        words = piece.split()
        while words and ("=" in words[0] or words[0] in ("sudo", "env", "time", "nohup")):
            words.pop(0)  # leading VAR=x and wrappers are not the command
        if not words:
            continue
        verb = words[0].rsplit("/", 1)[-1]
        # THE JOURNAL'S OWN COMMANDS ARE NEVER A WRITE. Declaring the work is the way OUT
        # of this gate, and a gate that blocks its own escape hatch is a locked room. But
        # only THAT PIECE is exempt: the first version waved through any line that
        # mentioned journal.py anywhere, so `journal todo "x" && rm -rf build` was not a
        # write. A chain is judged piece by piece, which is also what lets a to-do be
        # parked in the same line as the work that continues after it.
        if _is_journal_verb(verb):
            continue
        if verb in WRITE_CMDS:
            return True
        if verb == "sed" and "-i" in words:
            return True
        if verb == "git" and len(words) > 1 and words[1] in WRITE_GIT:
            return True
    return False


def _is_journal_verb(word: str) -> bool:
    return word == "journal" or word.endswith("journal.py")


def _pieces(cmd: str) -> list[list[str]]:
    """Each command of a chain as its words, quotes and heredoc bodies removed."""
    bare = _QUOTED.sub(" ", _HEREDOC.sub(" <<HEREDOC ", " ".join(cmd.split())))
    out = []
    for piece in _SPLIT.split(bare):
        words = piece.split()
        while words and ("=" in words[0] or words[0] in ("sudo", "env", "time", "nohup")):
            words.pop(0)
        if words:
            words[0] = words[0].rsplit("/", 1)[-1]
            out.append(words)
    return out


#: What a journal read is piped through. `journal --back=1 | head -40` is still reading.
_FILTERS = frozenset({"head", "tail", "grep", "cut", "wc", "sort", "uniq", "tr", "cat",
                      "less", "more", "fold", "column", "awk"})

#: The journal verbs that answer a context rung. A chain that OPENS with one of these has
#: decided before anything after it runs, so the rung gate lets the whole line through.
DECIDES = frozenset({"remember", "rule", "nothing"})


def _is_journal(payload: dict) -> bool:
    """May this call pass the rung gate? Journal-only lines, or a line that decides first.

    `journal search x` and `journal --back=1` are how the decision gets made, so a line of
    nothing but journal commands passes. `journal remember "…" && git commit` passes too:
    the decision runs first and lifts the gate before the commit. `ls && journal nothing
    "…"` does not — the `ls` would run undecided.
    """
    if (payload.get("tool_name") or "") != "Bash":
        return False
    pieces = _pieces(str((payload.get("tool_input") or {}).get("command", "")))
    if not pieces:
        return False
    if (any(_is_journal_verb(w[0]) for w in pieces)
            and all(_is_journal_verb(w[0]) or w[0] in _FILTERS for w in pieces)):
        return True
    first = pieces[0]
    return _is_journal_verb(first[0]) and len(first) > 1 and first[1] in DECIDES


#: Where a `remember` stops on a command line: the next shell separator or redirection.
_SEPARATORS = frozenset({"&&", "||", ";", "|", "&"})
_REDIRECT = re.compile(r"^\d*[<>]")

#: A HEREDOC BODY, ON THE RAW COMMAND. The opener's line is kept and everything from the
#: next line to the terminator is dropped. Distinct from `_HEREDOC` above, which runs on a
#: whitespace-collapsed line; this one needs the newlines to know where the body starts.
#: Caught live: a patch script piped through `python3 - <<'PY'` mentioned
#: `journal.py remember "<the claim>"` in a string, and the pin gate denied the patch.
_HEREDOC_BODY = re.compile(r"(<<-?\s*['\"]?(\w+)['\"]?[^\n]*)\n.*?(?:\n\2(?=\n|$)|\Z)", re.S)


def _pin_overflow(payload: dict, limit: int) -> str | None:
    """The refusal a `journal remember` on this command line would earn, before it runs.

    THE COMMAND'S OWN EXIT 1 WAS NOT ENOUGH. It is a line of stderr after the fact, and a
    reader in the middle of a thought reads past it and carries on believing the pin
    stands. A denied tool call is not readable past: the command never ran, and the reason
    is the whole of what comes back. So the fact is read off the command line here — the
    same tokens `journal.py` would join — and judged by the same function the CLI uses.

    If the line cannot be parsed it is left to the CLI: a gate that guesses at a quoting
    it did not understand would deny reads, and that is the failure this file keeps
    measuring. The miss costs one refused command; the guess costs trust in the gate.
    """
    if (payload.get("tool_name") or "") != "Bash":
        return None
    cmd = str((payload.get("tool_input") or {}).get("command", ""))
    if "journal" not in cmd or not ("remember" in cmd or "rule" in cmd):
        return None
    import shlex
    try:
        toks = shlex.split(_HEREDOC_BODY.sub(r"\1", cmd))
    except ValueError:
        return None
    for i, t in enumerate(toks):
        if t not in ("remember", "rule") or i == 0 or "journal" not in toks[i - 1]:
            continue
        fact = []
        for t2 in toks[i + 1:]:
            # A redirection ends the fact as surely as a pipe: `2>&1 | tail -1` was being
            # counted as five characters of claim.
            if t2 in _SEPARATORS or _REDIRECT.match(t2):
                break
            if t2.startswith("--"):
                continue
            fact.append(t2)
        return pins.refused(" ".join(fact), limit)
    return None


#: Journal verbs that WRITE. A subagent may read the record; it may not change it.
JOURNAL_WRITES = frozenset({"start", "end", "update", "remember", "strike", "switch", "nothing",
                            "rule", "promote", "todo"})


def _journal_write(payload: dict) -> str | None:
    """The journal write verb on this command line, if it is one, anywhere in a chain."""
    if (payload.get("tool_name") or "") != "Bash":
        return None
    if "journal" not in str((payload.get("tool_input") or {}).get("command", "")):
        return None
    import shlex
    try:
        toks = shlex.split(str((payload.get("tool_input") or {}).get("command", "")))
    except ValueError:
        return None
    for i, t in enumerate(toks[:-1]):
        if "journal" in t and toks[i + 1] in JOURNAL_WRITES:
            return toks[i + 1]
    return None


def _subagent(payload: dict) -> bool:
    """Is this event a subagent's? Its payload carries `agent_id`; the session's own do not.

    THE JOURNAL IS THE ORCHESTRATOR'S. A subagent is dispatched with a brief and reports
    back; what it decides is the orchestrator's to file, and what it reads fills its own
    window, not the one the marks are about. So a subagent's events file nothing, are held
    for nothing, and are nudged for nothing — and a subagent's attempt to WRITE the record
    is denied, because a pin nobody in the main conversation saw written is a fact of
    unknown provenance in the highest-authority position the system has.
    """
    return bool(payload.get("agent_id"))


def on_pre_tool(conf: dict, payload: dict, ctx: Ctx) -> int:
    """Refuse a write while no work is open. The one rule that stands IN THE PATH of an act.

    Everything else here is a nudge after the fact: the stop hook says a message went
    unfiled once it is already unfiled, and the agent can read past it. Measured on a live
    session doing eight hours of real work — 843 lines, every message dutifully tagged, and
    `journal start` run EXACTLY ZERO TIMES. The free thing got used and the costly one did
    not, which is what always happens when one rule is a side effect and the other is a
    discipline.

    So this is the second rule, and it is deliberate that there are now two. A gate is
    expensive — it stops work — and it earns that only where a nudge has been shown not to
    land. That evidence now exists.

    IT NAMES THE WAY OUT IN THE MESSAGE, and the way out is one command. A gate that says
    "denied" without saying how to proceed is an obstacle; one that hands you the next line
    is a prompt. And it never blocks `journal.py` itself, because declaring the work is the
    escape and a gate that locks its own door is a trap.
    """
    # The first event a transcript's hook sees is nearly always a tool call, so this is
    # where a session joined late gets its floor. One small read, once.
    _floor(ctx)
    over = _pin_overflow(payload, conf["pin_max_chars"])
    if over:
        return _deny("That pin would be refused, so the command is not run.\n" + over)
    # A RUNG WAS ANNOUNCED AND NOTHING WAS DECIDED. The hold at the stop was measured and
    # did not land — the user had to ask for the pin — so until `remember` or `nothing`
    # has run, no other tool does. Reads too, this once: the decision needs thought, not
    # more files, and the transcript stays readable through the journal's own commands,
    # which are never gated because they are the way out.
    due = state.get(ROOT, "pin_due", None, stem=ctx.stem)
    if due and not _is_journal(payload):
        pct = 100 * due["used"] / due["window"] if due.get("window") else 0
        return _deny(
            f"CONTEXT IS {pct:.0f}% FULL and nothing has been decided about what must "
            f"outlive it. This call is denied until one of these has run:\n"
            '  .journal/journal.py remember "<the claim, in one line>"\n'
            '  .journal/journal.py nothing "<why nothing here needs pinning>"\n'
            "Nothing is the right answer more often than not — say so and carry on. "
            "`journal search`, `journal --back=1` and `journal pins` still run, to decide with."
        )
    if not conf["gate_writes_on_start"] or "gate" in conf["silenced"]:
        return 0
    if not _is_write(payload) or work.open_work(ROOT):
        return 0
    return _deny(
        "Nothing is open, so this edit would not be filed against any work. Say what "
        "you are doing first — one line, and then this stops asking:\n"
        '  .journal/journal.py start "<the work, in your own words>"\n'
        "Close it with `end` when it is done. Reads are never gated; only changes."
    )


def _deny(reason: str) -> int:
    """Refuse the tool call, with the way out in the message. The one hold before an act."""
    print(json.dumps({"hookSpecificOutput": {
        "hookEventName": "PreToolUse",
        "permissionDecision": "deny",
        "permissionDecisionReason": reason,
    }}))
    return 0


def _response_size(payload: dict) -> int:
    """How much this tool actually handed back, in characters."""
    r = payload.get("tool_response")
    if isinstance(r, str):
        return len(r)
    if isinstance(r, dict):
        for key in ("stdout", "content", "output", "text"):
            v = r.get(key)
            if isinstance(v, str):
                return len(v)
            if isinstance(v, list):
                return sum(len(x.get("text", "")) for x in v if isinstance(x, dict))
    return len(json.dumps(r)) if r is not None else 0


def on_post_tool(conf: dict, payload: dict, ctx: Ctx) -> int:
    """Say what a tool call cost, at the moment it cost it — and almost never say it.

    THE SIZE IS A FACT AND THE COMMAND IS A GUESS. The first shape of this was going to
    check whether a bash line contained a `grep` or a `head`, and refuse it if not. That
    reads the intent instead of the result: a piped `grep` can still return forty thousand
    characters, and a bare `cat` of a short file costs nothing. What is worth saying is
    what actually came back, which is measured and cannot be argued with.

    IT SPEAKS ONLY ON A NEW RECORD. Not every large result — the LARGEST SO FAR in this
    context, above a floor. That is the rate limit, and it is self-decaying: the second
    40k read after a 60k one says nothing, and a session settles into silence on its own
    without a counter or an interval. Every rule in here that fired on a condition rather
    than a record ended up teaching the reader to skim it — eleven wrong nudges to catch
    three — and a per-tool complaint is the worst possible place for that, because it lands
    mid-thought where the agent is least able to weigh it.

    SUBAGENTS ARE OUT OF THIS. When this mark was project-wide, three critics reading the
    package raised it from 28,780 to 83,700 and the parent session was silenced by output
    it never saw. A subagent's events go to `on_subagent_post` instead, which hands it the
    rules and nothing else.
    """
    _floor(ctx)
    if "tool_cost" in conf["silenced"]:
        return 0
    floor = conf["tool_cost_floor"]
    if not floor:
        return 0
    size = _response_size(payload)
    if size < floor or size <= state.get(ROOT, "biggest_result", 0, stem=ctx.stem):
        return 0
    state.put(ROOT, "biggest_result", size, stem=ctx.stem)
    name = payload.get("tool_name") or "that tool"
    return _context(
        "PostToolUse",
        f"THAT {name} CALL RETURNED {size:,} CHARACTERS — the largest this session, and it "
        f"is in the context now for good.\n"
        f"If you need it, fine. If you were looking for one thing in it, the next one can "
        f"be narrower: grep for the line, sed a range, head the file. Nothing to run and "
        f"nothing to undo — this is said once per new record, not per call.",
    )


# `on_message_display` LIVED HERE and wrote `last_untagged`, which nothing ever read. The
# event is real in the harness but was never wired for this package, and a handler whose
# only output is a key nobody reads is a write that reports success and lands nowhere.


def _hold(brief: str, text: str) -> int:
    """Hold the stop: one line for the user, the reasoning for the agent.

    The first version did this with `exit 2` + stderr, which works — and which the harness
    renders to the user as `Stop hook error`. `decision: "block"` was the same hold said
    properly: exit 0, the turn continues so the agent can act. The harness still labels
    the block's reason an error on the user's screen, and prints ALL of it — twenty lines
    of reasoning about pins, every stop, in the user's terminal, for a nudge addressed to
    the agent.

    So the hold has two halves. `reason` is ONE LINE, and it is the whole instruction: what
    happened and the one thing to do, so an agent that received nothing else could still
    act. `additionalContext` carries the reasoning, which the harness delivers to the agent
    and folds away on the user's side. The user sees one line and can open it; the agent
    reads the rest.
    """
    print(json.dumps({
        "decision": "block",
        "reason": brief,
        "hookSpecificOutput": {"hookEventName": "Stop", "additionalContext": text},
    }))
    return 0


#: Events whose `hookSpecificOutput.additionalContext` the harness ACCEPTS. Measured, not
#: assumed: PreCompact is not among them, and emitting it there is rejected by schema
#: validation — the hook runs, exits 0, writes its state, and its payload is thrown away.
#: That is a third state past wired-and-fired: ACCEPTED. This list is the one place it
#: lives, so a handler cannot quietly address an event that will not listen.
DELIVERS_CONTEXT = frozenset({
    "UserPromptSubmit", "PostToolUse", "PostToolBatch", "Stop", "SubagentStop",
    "SessionStart",
})


def _context(event: str, text: str) -> int:
    """Hand the harness something to put in front of the agent.

    Refuses an event that cannot carry it. A rejected payload looks identical to a
    delivered one from in here — same exit 0, same written state — so the refusal is
    LOUD: it goes to stderr and to the user, because a delivery that fails invisibly is
    the one shape this system exists to prevent.
    """
    if event not in DELIVERS_CONTEXT:
        print(
            f"journal: {event} cannot carry additionalContext — the harness rejects it. "
            f"{len(text.splitlines())} lines were NOT delivered.",
            file=sys.stderr,
        )
        return 0
    print(json.dumps({"hookSpecificOutput": {"hookEventName": event,
                                             "additionalContext": text}}))
    return 0


# `on_pre_compact` LIVED HERE. PreCompact cannot shape the summary — the harness accepts no
# additionalContext on it, verified by having the payload rejected while the hook exited 0
# — and the one thing left for it to do was write `compacted_pending`, which nothing read.
# The bridge that delivers is SessionStart(source="compact"), on the far side of the loss.
# A doorbell wired to a handler that does nothing is the wired-and-silent shape `verify`
# exists to report, so the event is no longer wired at all.


def carried(source: str = "compact") -> str:
    """Exactly what a session is handed at its start, built without writing anything.

    THE INJECTED BLOCK IS THE ONE THING NOBODY COULD LOOK AT. It is assembled inside a
    hook, delivered to a context the user cannot read, and until now the only way to see it
    was to pipe a fake payload into the hook — which also wrote state, so looking changed
    the thing being looked at. A mechanism whose output is invisible until it fires is the
    shape this whole package exists to argue against, and this one had it.

    So the assembling lives here, pure, and the handler is what writes. `journal carry`
    reads it and nothing moves.

    THE STORE IS DELIVERED AT EVERY START. The journal is shared by every session, and a
    session that starts fresh, or after `/clear`, or as a fork, has lost as much as one that
    compacted. Only the closing paragraph about "the summary you are holding" is kept for a
    compaction, because on any other source there is no summary and a message claiming one
    is a nudge about an event that did not happen.
    """
    here = tracks.current(ROOT)
    parts = [
        # THE RULES ARE SAID AT THE START, NOT ONLY ENFORCED AT THE STOP. Until this, the
        # vocabulary reached the agent exactly one way: by being held for breaking it. A
        # system whose rules are learnable only through their own violation trains the
        # reader that a rule is something that appears after a mistake — and this one is
        # supposed to be the opposite of that.
        #
        # WHICH TRACK OF WORK THIS IS comes first: a fresh agent inherits a track it did
        # not choose and cannot see, and every pin and open item below belongs to that one.
        f"THE JOURNAL IS IN FORCE HERE — you are on track `{here}`"
        " (`journal tracks` for the others).\nOpen every message with exactly one tag:\n"
        + "\n".join(f"  [!{t.name}]  {t.line}" for t in tags.TAGS.values())
        + "\n\nThe tag is free — it rides on a message you were sending anyway. Work is "
        "not:\n"
        "  journal start \"<the work>\"     declare it\n"
        "  journal update \"<what moved>\"  progress on it — a command, never a tag\n"
        "  journal end \"<the same words>\" close it\n"
        "  journal remember \"<fact>\"      survives a compaction, on this track\n"
        "  journal rule \"<ruling>\"        survives on every track\n"
        "  journal todo \"<title>\"         delayed work, on this track\n"
        "WHEN THE USER ASKS FOR SOMETHING YOU ARE NOT WORKING ON AND IT CAN WAIT, park it: "
        "`journal todo \"<title>\"`, say you did, and carry on. It is listed at every start "
        "and picked up with `journal todo start <n>` when the user says so.\n\n"
        # THE BLOCK IS THE RULES; THE SKILL IS THE REASONING. This has to stay short — it
        # arrives at every session start and again after every compaction — so the why, the
        # refusals, and how to read the transcript back live in a skill that is loaded only
        # when somebody wants them.
        # THE REFLEX, AND IT IS THE HALF THAT WAS MISSING. Everything above tells the
        # agent how to WRITE the record. Nothing told it when to READ one — so the default
        # when asked about an earlier decision is to answer from whatever survived the
        # summary, confidently, which is exactly the failure the record exists to prevent.
        # A half-remembered ruling is worse than an admitted gap: it sounds like knowledge.
        "IF YOU ARE UNSURE WHAT WAS DECIDED, LOOK — do not answer from what survived:\n"
        "  journal search <term>   every line mentioning it, and who said it\n"
        "  journal --back=1        the stretch the last summary replaced\n"
        "  journal user            the user's own words, in full\n"
        "The transcript lost nothing. Checking costs one command; guessing costs the "
        "user their own decision.\n\n"
        # THE SKILL IS WHERE "WHEN" LIVES. This block can only hold the rules; the skill
        # says when to pin and when not to, when to search instead of answering from
        # memory, and what each hold means. A reader who never loads it learns the rules
        # by being held for them, which is the shape this block was written to end.
        "LOAD THE `journal` SKILL before your first pin, rule, declaration or search in "
        "this session — it says WHEN to do each, with examples — and again whenever a "
        "hook holds or denies you."
    ]
    # RULES BEFORE PINS. A rule binds every track, so a reader meets the constraints
    # before the facts of the one track they happen to be on.
    ruled = pins.carry(ROOT, source, key=pins.RULES)
    if ruled:
        parts.append(ruled)
    pinned = pins.carry(ROOT, source)
    if pinned:
        parts.append(pinned)
    standing = work.open_work(ROOT)
    if standing:
        parts.append("STILL OPEN, from this or an earlier session:\n"
                     + "\n".join(f"  - {w['subject']}" for w in standing)
                     + "\n`journal open` shows where each got to.")
    waiting = todo.carry(ROOT, here)
    if waiting:
        parts.append(waiting)
    if source == "compact":
        parts.append(
            "THE SUMMARY YOU ARE HOLDING DROPPED WHAT WAS DECIDED. Before you touch anything:\n"
            "  .journal/journal.py --back=1    the stretch that summary REPLACED\n"
            "  .journal/journal.py user        the user's own words, in full\n"
            "  .journal/journal.py open        work you declared and never closed\n"
            "The transcript lost nothing. Read it rather than half-remembering it."
        )
    return "\n\n".join(parts)


def on_subagent_post(conf: dict, payload: dict) -> int:
    """Hand a subagent the rules: on its first tool call, and again as its window fills.

    A RULE BINDS A SUBAGENT'S WORK. "A component is never a field on another component's
    State" is as true for the agent editing the PHP as for the one that dispatched it, and
    until this a subagent never saw it. Pins and open work stay out — those are the main
    conversation's, and the subagent cannot write the journal anyway — so this is rules
    only, with one line saying whose journal it is.

    NO SESSIONSTART FIRES FOR A SUBAGENT, so the block rides the first PostToolUse, which
    is measured to reach it. It comes back at the marks in `subagent_rules_ladder`, read
    from the SUBAGENT'S OWN transcript: the payload names the parent's, and the agent's
    sits one level down under it. Context, never a hold: there is nothing to decide.
    """
    aid = payload.get("agent_id") or ""
    stem = f"agent-{aid}"
    ruled = pins.live(ROOT, pins.RULES)
    if not ruled:
        return 0
    given = state.get(ROOT, "rules_at", None, stem=stem)
    passed: list[float] = []
    if given is None:
        given, passed = [], [0.0]
    else:
        own = transcript.find(ROOT.parent, stem)
        got = context.pressure(own, conf["context_window"]) if own else None
        if got and got[3]:
            passed = [r for r in sorted(conf["subagent_rules_ladder"]) if got[0] >= r and r not in given]
    if not passed:
        return 0
    # EVERY MARK CROSSED IS RECORDED, not only the highest: a step from 20% to 55% passes
    # two, and recording one would hand the block over again at the very next call.
    mark = passed[-1]
    state.put(ROOT, "rules_at", sorted(set(given) | set(passed)), stem=stem)
    lead = (
        "YOU ARE A SUBAGENT. The journal here is the main conversation's, not yours to write: "
        "report what you find and it decides what to file. These rules bind your work:"
        if mark == 0.0 else
        f"YOUR CONTEXT IS {mark:.0%} FULL. The rules of this project again, because a block "
        "read at the start is far behind you now:"
    )
    body = "\n".join(f"  - {r['fact']}" for r in ruled)
    return _context("PostToolUse", lead + "\n" + body)


def _prune() -> None:
    """Drop the runtime file of any transcript this machine no longer has.

    BY EVIDENCE, NEVER BY A COUNTER. A file is kept as long as its transcript is, however
    old, because `verify` counts these as proof the hook ran and nothing here deletes what
    it cannot account for. Subagent transcripts live one level down and are found there.
    Only `*.json` is touched: a writer's tmp is somebody else's file mid-flight.
    """
    project = ROOT.parent
    for stem, _ in state.runtime_files(ROOT):
        if transcript.find(project, stem) is None:
            try:
                state.runtime_file(ROOT, stem).unlink()
            except OSError:
                pass


def on_session_start(conf: dict, payload: dict, ctx: Ctx) -> int:
    """Hand the session the store, and mark that this hook is alive in this transcript.

    EVIDENCE THAT THIS RAN, written by the only thing that can write it. Until now the
    only proof a hook had fired was a HOLD, so a journal doing its job quietly — teaching
    the vocabulary at every session start and never needing to hold anybody — was
    indistinguishable from one that had never been invoked. `verify` would have called it
    dead. A hook that works has to leave a mark, or the check that looks for marks is
    measuring how often the agent misbehaves rather than whether the mechanism is alive.
    """
    source = payload.get("source") or "startup"
    _floor(ctx)
    state.put(ROOT, "session_started", source, stem=ctx.stem)
    _prune()
    return _context("SessionStart", carried(source))


#: EVERY EVENT, IN ONE TABLE. The harness has to name this script once per event it should
#: hear about — that part is its rule, not ours — but the script is a single door, and what
#: happens behind it is decided in exactly one place.
#:
#: Every handler takes the same triple — settings, payload, and which transcript this is —
#: and uses what it needs, so the table is the whole routing story. The lowercase spellings
#: are the same events as the harness has also spelled them; an unknown event is silence,
#: because a doorbell that argues with a caller it does not recognise is worse than one
#: that does not ring.
HANDLERS = {
    "Stop": on_stop,
    "stop": on_stop,
    "SessionStart": on_session_start,
    "session-start": on_session_start,
    "PreToolUse": on_pre_tool,
    "pre-tool-use": on_pre_tool,
    "PostToolUse": on_post_tool,
    "post-tool-use": on_post_tool,
}


def main() -> int:
    try:
        payload = json.load(sys.stdin)
    except Exception:
        return 0  # a doorbell that crashes on a payload it did not expect is worse than none
    conf, problems = settings_mod.load(ROOT)
    for p in problems:
        print(f"journal: {p}", file=sys.stderr)

    event = payload.get("hook_event_name") or payload.get("event") or ""
    handler = HANDLERS.get(event)
    if handler is None:
        return 0
    state.retire_old(ROOT)
    # SUBAGENTS ARE OUT, AT THE DOOR. Measured across ten subagent transcripts: Stop does
    # not fire for them today, only the tool events do. Closing every event here rather
    # than inside two handlers means a harness that starts firing more of them changes
    # nothing. The one thing a subagent's event can still do is be refused a journal write.
    if _subagent(payload):
        if handler is on_pre_tool:
            verb = _journal_write(payload)
            if verb:
                return _deny(
                    f"`journal {verb}` from a subagent is refused: the journal is the main "
                    f"conversation's. Report what you found and let it decide what to file. "
                    f"Reads (`search`, `pins`, `open`, `--back`) are fine."
                )
            return 0
        if handler is on_post_tool:
            return on_subagent_post(conf, payload)
        return 0
    ctx = _ctx(payload)
    if ctx is None:
        print(f"journal: {event} payload names no session or transcript — nothing filed",
              file=sys.stderr)
        return 0
    # A CRASH IS WORSE THAN SILENCE. A traceback here is rendered to the user as a hook
    # error, which teaches that the journal is broken where it was only surprised. Say
    # what happened on stderr and let the turn go on.
    try:
        return handler(conf, payload, ctx)
    except Exception as e:  # noqa: BLE001
        print(f"journal: {event} handler failed ({type(e).__name__}: {e}) — nothing filed",
              file=sys.stderr)
        return 0


if __name__ == "__main__":
    raise SystemExit(main())
