"""Reading the session's own .jsonl — the record the journal indexes.

THE TRANSCRIPT IS THE RECORD. The journal never copies it. A compaction rewrites the
conversation into a summary of what was DONE and drops what was DECIDED; the .jsonl on
disk lost nothing. Everything here exists to get back to it.
"""
from __future__ import annotations

import json
import os
from dataclasses import dataclass, field
from pathlib import Path

PROJECTS = Path.home() / ".claude" / "projects"


def project_dir(cwd: Path) -> Path:
    """Claude Code's folder for a project: the absolute path with / as -."""
    return PROJECTS / ("-" + str(cwd.resolve()).strip("/").replace("/", "-"))


def newest_session(cwd: Path) -> Path | None:
    """The transcript most recently written for this project — A GUESS, and the last resort.

    With two terminals open on one project this flips between them, and every reader that
    trusted it read the other terminal's conversation: the hook held session A for session
    B's messages, and `remember` stamped a pin with B's line number. The hook now takes its
    transcript from the payload and the CLI from `CLAUDE_CODE_SESSION_ID`; this is what is
    left for a person at a bare terminal, and its caller says that it guessed.
    """
    d = project_dir(cwd)
    if not d.is_dir():
        return None
    files = [f for f in d.glob("*.jsonl") if f.is_file()]
    return max(files, key=lambda f: f.stat().st_mtime, default=None)


#: The environment variable every Bash call made from inside a session carries. Inside a
#: subagent it is the PARENT's id, measured — so a pin written from a subagent cites the
#: parent's transcript at the moment the agent was running, which is where the reader who
#: follows the citation will find the dispatch. That is stated here rather than hidden.
SESSION_ENV = "CLAUDE_CODE_SESSION_ID"


def find(cwd: Path, stem: str) -> Path | None:
    """The transcript with this stem: a session's own file, or a subagent's under it.

    Subagent transcripts live one level down, at `<session>/subagents/agent-<id>.jsonl`,
    and a check that only looked at the top level would call every one of them gone.
    """
    d = project_dir(cwd)
    top = d / f"{stem}.jsonl"
    if top.is_file():
        return top
    for f in d.glob(f"*/subagents/{stem}.jsonl"):
        if f.is_file():
            return f
    return None


def session_transcript(cwd: Path) -> tuple[Path, bool] | None:
    """(the transcript this process belongs to, whether it was guessed).

    Exact when the session id is in the environment and its file exists; otherwise the
    newest by mtime, flagged as a guess so the output can say so.
    """
    sid = os.environ.get(SESSION_ENV, "")
    if sid:
        got = find(cwd, sid)
        if got is not None:
            return got, False
    got = newest_session(cwd)
    return (got, True) if got is not None else None


@dataclass
class Line:
    """One thing somebody said, or one thing the machine did.

    `n` is the line's index in the transcript and is the citation the whole system quotes:
    a brief that says "turn 412" is checkable, and one that says "earlier" is not.
    """

    n: int
    role: str  # user | assistant | system
    kind: str  # human | tool_result | injected | peer | task | text | superseded
    text: str
    ts: str
    tags: list[str] = field(default_factory=list)
    tools: list[str] = field(default_factory=list)
    parent: str = ""  # the record this one answers; two prompts sharing it are one prompt

    @property
    def spoken(self) -> bool:
        """Did a person or the agent SAY this, as opposed to the machine reporting it?

        Tool results and hook injections are the bulk of a transcript and none of it is
        anybody's words. Reading back is only ever worth doing over the half somebody said.
        """
        return self.kind in ("human", "text")


#: Tools whose call IS a message to the user, and whose result IS the user's reply. A
#: question asked through one of these lives in the tool_use input, and the answer comes
#: back as a tool_result — neither is a text block, so a reader of text blocks alone shows
#: the user answering a question that was never asked. Measured: "can you ask again",
#: then the question, then "I believe I answered with what I want", with nothing between.
ASKS = frozenset({"AskUserQuestion"})


def _asked(inp: dict) -> str:
    """The question tool's input, as the words the user saw."""
    out = []
    for q in (inp or {}).get("questions") or []:
        if not isinstance(q, dict):
            continue
        line = str(q.get("question", "")).strip()
        opts = [str(o.get("label", "")) for o in q.get("options") or [] if isinstance(o, dict)]
        if opts:
            line += "  [" + " / ".join(o for o in opts if o) + "]"
        out.append(line)
    return "\n".join(f"asked: {q}" for q in out if q)


def _text_of(msg: dict) -> tuple[str, list[str], dict, list[str]]:
    """(text, tool names, {tool_use id: name} for questions, tool_use ids answered here)."""
    content = msg.get("content")
    if isinstance(content, str):
        return content, [], {}, []
    out, tools, asks, answered = [], [], {}, []
    for block in content or []:
        t = block.get("type")
        if t == "text":
            out.append(block.get("text", ""))
        elif t == "tool_use":
            name = block.get("name", "?")
            tools.append(name)
            if name in ASKS:
                asks[block.get("id", "")] = name
                out.append(_asked(block.get("input") or {}))
        elif t == "tool_result":
            answered.append(block.get("tool_use_id", ""))
            c = block.get("content")
            if isinstance(c, str):
                out.append(c)
            elif isinstance(c, list):
                out.extend(x.get("text", "") for x in c if isinstance(x, dict))
    return "\n".join(x for x in out if x), tools, asks, answered


#: Hook events that happen where the assistant STOPPED. This is the whole point of reading
#: hook records at all: a turn ends at a stop, and a stop is either the user speaking or a
#: hook interrupting. PostToolUse and PreToolUse are deliberately absent — they fire in the
#: MIDDLE of a turn, and counting one as a boundary would cut a turn in half and promote a
#: connective opener into the message. That is not hypothetical: the project this was first
#: read in has a PostToolUse hook that injects on 23 tool calls.
STOP_EVENTS = frozenset({"Stop", "SubagentStop", "SessionStart"})


def _hook_line(rec: dict) -> tuple[str, str] | None:
    """A hook's injection, when the transcript files it as an `attachment` record.

    TWO TRANSCRIPT SHAPES EXIST. In one, a hook's context arrives as a user record and is
    read like any other line. In the other it is an `attachment`, a type this reader used to
    skip entirely — so the journal could not see its own footprints. Measured in a live
    session: 173 attachment records ignored, turn boundaries lost with them, and 181
    assistant messages collapsing into 3 filing units because nothing marked where the turns
    ended. The check went quiet and every green light stayed green.

    `hook_additional_context` is what actually reached the model. `hook_success` is the raw
    result and would double every event, so it is taken only for a Stop — which cannot carry
    additionalContext at all, and whose hold therefore appears nowhere else.
    """
    a = rec.get("attachment") or {}
    kind, event = a.get("type"), a.get("hookEvent")
    if event not in STOP_EVENTS:
        return None
    if kind == "hook_additional_context":
        c = a.get("content")
        return ("\n".join(x for x in c if isinstance(x, str)) if isinstance(c, list)
                else str(c or "")), rec.get("timestamp", "")
    if kind == "hook_success" and event in ("Stop", "SubagentStop"):
        return str(a.get("stdout") or ""), rec.get("timestamp", "")
    return None


def _kind(rec: dict, has_tool_result: bool) -> str:
    if rec.get("type") == "assistant":
        return "text"
    origin = (rec.get("origin") or {}).get("kind")
    if origin == "human":
        return "human"
    if origin == "peer":
        return "peer"
    if origin == "task-notification":
        return "task"
    if has_tool_result:
        return "tool_result"
    return "injected"


def read(path: Path) -> tuple[list[Line], list[int]]:
    """Every line, and the indices where a compaction fell.

    The boundaries are what make `--back=N` possible: a summary is not a thing you can
    read, but the stretch it REPLACED is, and it is the stretch that was dropped.
    """
    lines: list[Line] = []
    boundaries: list[int] = []
    n = 0
    asked: set[str] = set()  # tool_use ids of questions put to the user, awaiting answers
    if not path.is_file():
        return lines, boundaries  # a transcript not yet written has no lines, not an error
    with path.open() as fh:
        for raw in fh:
            raw = raw.strip()
            if not raw:
                continue
            try:
                rec = json.loads(raw)
            except ValueError:
                continue  # a half-written line is not a reason to lose the rest
            typ = rec.get("type")
            if typ == "system" and rec.get("subtype") == "compact_boundary":
                boundaries.append(n)
                continue
            if typ == "attachment":
                got = _hook_line(rec)
                if got is None:
                    continue
                n += 1
                lines.append(Line(n=n, role="user", kind="injected",
                                  text=got[0], ts=got[1]))
                continue
            if typ not in ("user", "assistant"):
                continue
            msg = rec.get("message") or {}
            text, tools, asks, answered = _text_of(msg)
            asked |= set(asks)
            content = msg.get("content")
            has_result = isinstance(content, list) and any(
                b.get("type") == "tool_result" for b in content
            )
            kind = _kind(rec, has_result)
            # THE ANSWER TO A QUESTION IS THE USER'S OWN WORDS, however the harness filed
            # it. It arrives as a tool_result, and a tool_result is the one kind the reader
            # treats as nobody's speech — so `journal user` lost every choice the user
            # made through the question tool. Filed as human, because it is.
            if kind == "tool_result" and any(a in asked for a in answered):
                kind = "human"
            n += 1
            line = Line(
                n=n,
                role=msg.get("role", typ),
                kind=kind,
                text=text,
                ts=rec.get("timestamp", ""),
                tools=tools,
                parent=str(rec.get("parentUuid") or ""),
            )
            # THE SAME PROMPT, RECORDED TWICE. A message sent mid-turn is filed when it is
            # queued and again when it becomes the prompt, and one edited before the agent
            # answered is filed in each version — every copy answering the SAME parent
            # record. Measured: "dont start building it yet yhough", "…though", "…though.
            # First come back with a design", three lines for one thought. The last copy
            # is the one the agent answered, so the earlier ones are marked superseded and
            # stay in place: numbering is a citation, and dropping a record would shift
            # every line after it.
            if line.kind == "human" and line.parent:
                for prev in reversed(lines):
                    if prev.kind == "text" and (prev.text or "").strip():
                        break  # the agent answered in between: a genuinely new prompt
                    if prev.kind == "human" and prev.parent == line.parent:
                        prev.kind = "superseded"
                        break
            lines.append(line)
    return lines, boundaries


def since(lines: list[Line], boundaries: list[int], back: int = 0) -> list[Line]:
    """The stretch `back` compactions ago. 0 is now; 1 is what the last summary replaced."""
    if not boundaries:
        return lines
    marks = [0] + boundaries + [len(lines)]
    i = len(marks) - 2 - back
    if i < 0:
        i = 0
    lo, hi = marks[i], marks[i + 1]
    return [l for l in lines if lo < l.n <= hi]


#: How the harness records the user pressing stop: a user-role record whose text is this
#: marker, as an injected line when a message was interrupted and as a tool result when a
#: tool call was. A STORAGE FORMAT, like a tag's spelling — it is what the transcript says.
INTERRUPTED = "[Request interrupted by user"


def interrupted(line: Line) -> bool:
    return line.role == "user" and (line.text or "").lstrip().startswith(INTERRUPTED)


def filing_units(lines: list[Line]) -> set[int]:
    """The line numbers that are actually MESSAGES, in the sense a reader means.

    NOT every assistant text block. A turn is one answer delivered in several pieces: a
    ten-word line saying what the next tool call is for, the tool call, another line, and
    finally the thing the user actually reads. Only the last of those is the message; the
    rest is scaffolding.

    Measured, not assumed. Fourteen consecutive holds in one session, eleven of which were
    lines like "Now wiring it into the CLI" — and the three that mattered were all the last
    block of their turn. A rule that fires eleven times wrongly to catch three teaches the
    reader to clear the nudge without reading it, and then the three go past too.

    So: within each turn — everything between one stop and the next — the last non-empty
    assistant text is the filing unit. The final turn has nothing after it, which is exactly
    the turn the stop hook is judging.

    IT LIVES HERE, NOT IN THE HOOK, BECAUSE TWO READERS HAVE TO AGREE. The hook decides
    what to hold on and the digest decides what to render; when each carried its own idea
    of "a message" the digest went on printing scaffolding the hook had already stopped
    filing, and a reader comparing the two would have found the system disagreeing with
    itself about its own central noun. One definition, in the module that owns the shape.
    """
    units: set[int] = set()
    current: int | None = None
    for l in lines:
        # AN INTERRUPTED TURN HAS NO MESSAGE. The user pressed stop mid-thought, so the
        # last text before the marker is a connective line that never got its ending —
        # "Probing the suspected bug directly:" — and holding for it accuses the agent of
        # an omission it was not allowed to finish. Measured twice in one day, in two
        # projects, each time on a line that opened a tool call. The turn is dropped, not
        # filed: nothing in it was delivered as an answer.
        if interrupted(l):
            current = None
            continue
        # A TURN ENDS WHEREVER THE ASSISTANT STOPPED, and it did not always stop because
        # the user spoke. A hook hold arrives as `injected`, and so does the SessionStart
        # block after a compaction — both land at a stop, which means the message before
        # them was DELIVERED and read. Boundarying only on `human` merged a held turn into
        # the next one and quietly demoted its final message to scaffolding. Caught by this
        # rule clearing a line it was written to catch.
        # A TASK NOTIFICATION IS A STOP TOO, and so is a peer's message: they are delivered
        # only when the assistant has finished. Measured: the direct answer to the user's
        # question, then a background agent's notification, then one more line — and the
        # answer was demoted to scaffolding and dropped from the digest as a routine reply.
        # Every user-role record that is not a tool result ends a turn.
        if l.role == "user" and l.kind != "tool_result":
            if current is not None:
                units.add(current)
            current = None
        elif l.kind == "text" and (l.text or "").strip():
            current = l.n
    if current is not None:
        units.add(current)
    return units
