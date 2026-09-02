#!/usr/bin/env python3
"""The record is shared; the marks are one transcript's. What that must never get wrong.

    .journal/test_state.py

THIS SUITE EXISTS BECAUSE THE MARKS WERE PROJECT-WIDE FOR WEEKS AND NOBODY COULD SEE IT.
A session at line 53 inherited `held_at: 1746` from the one before and its untagged hold
could not fire until line 1747; a subagent's read raised `biggest_result` in the parent's
context; the 50% rung, announced once per project, was never announced again. Every green
light stayed green, because the failure was silence.

Every test runs against a throwaway directory. It never touches the real record. The hook
is driven as a subprocess with a hand-built payload, exactly as the harness drives it.
"""
import json, os, shutil, subprocess, sys, tempfile
from pathlib import Path

SRC = Path(__file__).resolve().parent
sys.path.insert(0, str(SRC))
import state, pins, work, tracks, hook, transcript, digest, todo  # noqa: E402

AT = "2026-09-01T12:00:00+00:00"
ok = fail = 0


def check(label, got, want):
    global ok, fail
    if got == want:
        ok += 1
    else:
        fail += 1
        print(f"  FAIL {label}\n       got  {got!r}\n       want {want!r}")


def fresh() -> Path:
    return Path(tempfile.mkdtemp())


# ------------------------------------------------------------- two scopes
r = fresh()
state.put(r, "held_at", 40, stem="A")
state.put(r, "held_at", 7, stem="B")
pins.add(r, "shared", AT, 140)
check("A's mark is A's", state.get(r, "held_at", 0, stem="A"), 40)
check("B's mark is B's", state.get(r, "held_at", 0, stem="B"), 7)
check("a third transcript starts at nothing", state.get(r, "held_at", 0, stem="C"), 0)
check("the record is the same from every transcript",
      [p["fact"] for p in pins.live(r)], ["shared"])
check("runtime files are named by the stem",
      sorted(f.name for f in (r / "runtime").iterdir()), ["A.json", "B.json"])
check("a record key never lands in runtime",
      "pins" in state.runtime(r, "A"), False)
check("a runtime write with no stem is refused, not filed at project scope",
      (state.put(r, "held_at", 99), state.get(r, "held_at", 0, stem="A")), (None, 40))
check("a runtime read with no stem is the default", state.get(r, "held_at", -1), -1)
check("runtime_files lists every transcript",
      [s for s, _ in state.runtime_files(r)], ["A", "B"])

# ------------------------------------------------------ the old file retires
r = fresh()
(r / "state.json").write_text(json.dumps({"held_at": 1746, "baseline_at": 1939}))
check("retire moves the old file aside", state.retire_old(r), True)
check("and nothing of it is read", state.get(r, "held_at", 0, stem="A"), 0)
check("retired twice is quiet", state.retire_old(r), False)
check("the old marks are still on disk for a person to read",
      json.loads((r / "state.json.retired").read_text())["held_at"], 1746)

# ---------------------------------------------------- concurrent writers
WRITER = """
import sys, json
sys.path.insert(0, %r)
import state, pins
from pathlib import Path
root, mode, i = Path(sys.argv[1]), sys.argv[2], sys.argv[3]
if mode == "runtime":
    state.put(root, "biggest_result", int(i), stem="A")
else:
    ok, msg = pins.add(root, "pin " + i, %r, 140)
    assert ok, msg
""" % (str(SRC), AT)
for mode, label in (("runtime", "runtime"), ("record", "record")):
    r = fresh()
    procs = [subprocess.Popen([sys.executable, "-c", WRITER, str(r), mode, str(i)],
                              stdout=subprocess.PIPE, stderr=subprocess.PIPE)
             for i in range(8)]
    outs = [p.communicate() for p in procs]
    codes = [p.returncode for p in procs]
    check(f"eight concurrent {label} writers, none crash", codes, [0] * 8)
    if mode == "runtime":
        check("the runtime file is whole afterwards",
              isinstance(state.get(r, "biggest_result", None, stem="A"), int), True)
        check("no tmp file was left behind",
              [f.name for f in (r / "runtime").iterdir() if f.suffix == ".tmp"], [])
    else:
        check("all eight pins stand — none lost to a race",
              sorted(p["fact"] for p in pins.live(r)), sorted(f"pin {i}" for i in range(8)))

# switch racing remember: the pin lands on the track that was current under the lock
r = fresh()
pins.add(r, "before", AT, 140)
SWITCHER = f"""
import sys; sys.path.insert(0, {str(SRC)!r})
import tracks; from pathlib import Path
for i in range(20):
    tracks.switch(Path(sys.argv[1]), "t%d" % (i % 2), {AT!r})
"""
PINNER = f"""
import sys; sys.path.insert(0, {str(SRC)!r})
import pins; from pathlib import Path
for i in range(20):
    ok, msg = pins.add(Path(sys.argv[1]), "race %d" % i, {AT!r}, 140)
    assert ok, msg
"""
ps = [subprocess.Popen([sys.executable, "-c", code, str(r)], stdout=subprocess.PIPE,
                       stderr=subprocess.PIPE) for code in (SWITCHER, PINNER)]
errs = [p.communicate()[1].decode() for p in ps]
check("switch and remember racing: neither crashes", [p.returncode for p in ps], [0, 0])
rec = json.loads((r / "record.json").read_text())
every = [p["fact"] for p in rec.get("pins", [])] + [
    p["fact"] for t in rec.get("tracks", {}).values() for p in t.get("pins", [])]
check("every pin written under the race exists on SOME track, none vanished",
      sorted(every), sorted(["before"] + [f"race {i}" for i in range(20)]))

# the lock is reentrant
r = fresh()
with state.locked(r):
    with state.locked(r):
        pins.add(r, "nested", AT, 140)
check("nested locking does not deadlock and still writes", [p["fact"] for p in pins.live(r)],
      ["nested"])

# -------------------------------------------------------- the hook, end to end
def project_with(lines: int, stem: str = "s1", tagged: bool = False):
    """A throwaway project whose transcript dir the hook will resolve from its own path."""
    d = Path(tempfile.mkdtemp()) / "proj"
    (d / ".claude").mkdir(parents=True)
    shutil.copytree(SRC, d / ".journal",
                    ignore=shutil.ignore_patterns("runtime", "state.json*", "record.json*",
                                                  "__pycache__"))
    tdir = transcript.project_dir(d)
    tdir.mkdir(parents=True, exist_ok=True)
    path = tdir / f"{stem}.jsonl"
    with path.open("w") as fh:
        for i in range(lines):
            if i % 2 == 0:
                fh.write(json.dumps({"type": "user", "origin": {"kind": "human"},
                                     "message": {"role": "user", "content": f"q{i}"}}) + "\n")
            else:
                fh.write(json.dumps({"type": "assistant", "message": {
                    "role": "assistant", "content": [{"type": "text", "text": ("[!reply] " if tagged else "") + f"line {i}"}],
                    "usage": {"input_tokens": 1000}}}) + "\n")
    return d, path


def fire(d, event, path, **extra):
    payload = {"hook_event_name": event, "session_id": path.stem,
               "transcript_path": str(path), **extra}
    p = subprocess.run([str(d / ".journal" / "hook.py")], input=json.dumps(payload),
                       capture_output=True, text=True)
    return p.returncode, p.stdout, p.stderr


def held(out: str) -> tuple[str, str]:
    """(the one line the user sees, the reasoning the agent reads) of a Stop hold."""
    if not out.strip():
        return "", ""
    d = json.loads(out)
    return d.get("reason", ""), (d.get("hookSpecificOutput") or {}).get("additionalContext", "")


def runtime_of(d, stem):
    return state.runtime(d / ".journal", stem)


# a fresh transcript is held on its first untagged message, at line 3 not line 1940
d, path = project_with(0)
fire(d, "SessionStart", path, source="startup")
check("SessionStart writes the floor at the line count then (0)", runtime_of(d, "s1")["floor"], 0)
with path.open("a") as fh:
    fh.write(json.dumps({"type": "user", "origin": {"kind": "human"},
                         "message": {"role": "user", "content": "hi"}}) + "\n")
    fh.write(json.dumps({"type": "assistant", "message": {
        "role": "assistant", "content": [{"type": "text", "text": "no tag here"}],
        "usage": {"input_tokens": 10}}}) + "\n")
code, out, err = fire(d, "Stop", path)
brief, why = held(out)
check("a fresh transcript is held on its first untagged message",
      (code, "carried no tag" in why), (0, True))
check("the user's line is one line and says what to do",
      (brief.count("\n"), brief.startswith("journal: 1 message(s) carried no tag")), (0, True))
check("the reasoning rides as context, not as the reason", ("[!discovery]" in why, "[!discovery]" in brief), (True, True))
check("the hold is recorded in THIS transcript's file", runtime_of(d, "s1").get("held_at"), 2)
check("and not in any other", runtime_of(d, "other"), {})

# a second transcript in the same project starts clean
path2 = path.with_name("s2.jsonl")
shutil.copy(path, path2)
code, out, err = fire(d, "Stop", path2)
check("Stop as the FIRST event on a transcript writes a floor, holds nothing",
      (code, out.strip(), runtime_of(d, "s2").get("floor")), (0, "", 2))
check("s1's mark is untouched by s2", runtime_of(d, "s1").get("held_at"), 2)

# a session joined late (hook wired at line 400) is not held for its history
d, path = project_with(400)
code, out, err = fire(d, "PreToolUse", path, tool_name="Read", tool_input={})
check("PreToolUse on first sight writes the floor at 400", runtime_of(d, "s1")["floor"], 400)
code, out, err = fire(d, "Stop", path)
check("and the 200 untagged messages before it are not held", out.strip(), "")

# subagents get their own marks, keyed by their agent id
d, path = project_with(4)
big = {"tool_name": "Bash", "tool_input": {}, "tool_response": {"stdout": "x" * 30000}}
code, out, err = fire(d, "PostToolUse", path, agent_id="abc", **big)
check("a subagent's read files nothing and is nudged for nothing",
      (out.strip(), runtime_of(d, "agent-abc"), runtime_of(d, "s1").get("biggest_result")),
      ("", {}, None))
code, out, err = fire(d, "PostToolUse", path, **big)
check("so the parent is still told about ITS first big read", "CHARACTERS" in out, True)
for cmd, want in (('.journal/journal.py remember "a fact"', True),
                  ('cd x && ./.journal/journal.py start "work"', True),
                  ('.journal/journal.py search remember', False),
                  ('.journal/journal.py pins', False),
                  ('cat file.py', False)):
    code, out, err = fire(d, "PreToolUse", path, agent_id="abc", tool_name="Bash",
                          tool_input={"command": cmd})
    check(f"subagent journal write denied, reads and other tools not: {cmd[:40]}",
          "from a subagent is refused" in out, want)
code, out, err = fire(d, "PreToolUse", path, agent_id="abc", tool_name="Edit", tool_input={})
check("a subagent's edit is not gated on open work", out.strip(), "")

# the context ladder is silent while the window is unknown, and climbs when set
d, path = project_with(4)
with path.open("a") as fh:
    fh.write(json.dumps({"type": "assistant", "message": {
        "role": "assistant", "content": [{"type": "text", "text": "[!reply] fine"}],
        "usage": {"input_tokens": 120000}}}) + "\n")
fire(d, "SessionStart", path, source="startup")
code, out, err = fire(d, "Stop", path)
check("120k tokens with the window unknown: no rung", "CONTEXT IS" in out, False)
(d / ".journal" / "settings.json").write_text(json.dumps({"context_window": 200000}))
code, out, err = fire(d, "Stop", path)
check("the same reading with a 200k window set: the 50% rung", "CONTEXT IS 60% FULL" in out, True)
check("the rung is this transcript's", runtime_of(d, "s1").get("warned_at"), 0.5)
(d / ".journal" / "settings.json").write_text(json.dumps({"context_window": 1000000}))
d2, path2 = project_with(4)
with path2.open("a") as fh:
    fh.write(json.dumps({"type": "assistant", "message": {
        "role": "assistant", "content": [{"type": "text", "text": "[!reply] fine"}],
        "usage": {"input_tokens": 250000}}}) + "\n")
code, out, err = fire(d2, "Stop", path2)
check("a peak past 200k rules the window in on its own: 25% of 1M, no rung",
      "CONTEXT IS" in out, False)

# after a rung, nothing runs until a decision: remember or nothing
d, path = project_with(4, tagged=True)
with path.open("a") as fh:
    fh.write(json.dumps({"type": "assistant", "message": {
        "role": "assistant", "content": [{"type": "text", "text": "[!reply] fine"}],
        "usage": {"input_tokens": 120000}}}) + "\n")
(d / ".journal" / "settings.json").write_text(json.dumps({"context_window": 200000}))
fire(d, "SessionStart", path, source="startup")
code, out, err = fire(d, "Stop", path)
check("the rung says the gate is coming", "NOTHING ELSE RUNS UNTIL" in out, True)
check("and records that a pin is due", runtime_of(d, "s1").get("pin_due", {}).get("rung"), 0.5)
code, out, err = fire(d, "PreToolUse", path, tool_name="Read", tool_input={"file_path": "x"})
check("a Read is denied while a pin is due",
      ("deny" in out, "nothing has been decided" in out), (True, True))
code, out, err = fire(d, "PreToolUse", path, tool_name="Bash",
                      tool_input={"command": ".journal/journal.py search thing"})
check("the journal's own commands still run", out.strip(), "")
code, out, err = fire(d, "PreToolUse", path, tool_name="Bash",
                      tool_input={"command": "ls"}, agent_id="sub")
check("a subagent's calls are not gated by the parent's rung", out.strip(), "")
J = str(d / ".journal" / "journal.py")
env = {**os.environ, transcript.SESSION_ENV: "s1"}
p = subprocess.run([J, "nothing"], env=env, capture_output=True, text=True)
check("nothing without a reason is refused", (p.returncode, "wants a reason" in p.stderr), (1, True))
check("and the gate still stands", "pin_due" in runtime_of(d, "s1") and runtime_of(d, "s1")["pin_due"] is not None, True)
p = subprocess.run([J, "nothing", "this stretch only read files, no ruling was made"],
                   env=env, capture_output=True, text=True)
check("nothing with a reason is accepted", (p.returncode, "noted" in p.stdout), (0, True))
check("and lifts the gate", runtime_of(d, "s1").get("pin_due"), None)
check("the decision is on the record", runtime_of(d, "s1")["pin_decided"]["how"].startswith("declined"), True)
code, out, err = fire(d, "PreToolUse", path, tool_name="Read", tool_input={"file_path": "x"})
check("a Read runs again", out.strip(), "")
p = subprocess.run([J, "nothing", "again"], env=env, capture_output=True, text=True)
check("nothing with no rung waiting says so", (p.returncode, "no pin is due" in p.stderr), (1, True))
# a pin lifts it too, and an over-long one does not
runtime = d / ".journal" / "runtime" / "s1.json"
data = json.loads(runtime.read_text()); data["pin_due"] = {"rung": 0.7, "used": 1, "window": 2}
runtime.write_text(json.dumps(data))
p = subprocess.run([J, "remember", "x" * 600], env=env, capture_output=True, text=True)
check("a refused pin does not lift the gate", runtime_of(d, "s1").get("pin_due") is not None, True)
p = subprocess.run([J, "remember", "the report is in scratchpad/report.md"], env=env,
                   capture_output=True, text=True)
check("a pin citing a scratch path is refused, with the reason",
      (p.returncode, "exists for one session only" in p.stderr), (1, True))
p = subprocess.run([J, "remember", "a real claim"], env=env, capture_output=True, text=True)
check("an accepted pin lifts the gate", runtime_of(d, "s1").get("pin_due"), None)
# and the whole thing is a setting
(d / ".journal" / "settings.json").write_text(json.dumps({"context_window": 200000,
                                                          "gate_after_context_rung": False}))
data = json.loads(runtime.read_text()); data["warned_at"] = 0.0; runtime.write_text(json.dumps(data))
code, out, err = fire(d, "Stop", path)
check("with the setting off the rung nudges and gates nothing",
      ("CONTEXT IS" in out, "NOTHING ELSE RUNS" in out, runtime_of(d, "s1").get("pin_due")),
      (True, False, None))

# an interrupted turn has no message to judge
L = transcript.Line
turn = [
    L(1, "user", "human", "do the thing", ""),
    L(2, "assistant", "text", "Looking at the file first.", ""),
    L(3, "user", "tool_result", "contents", ""),
    L(4, "assistant", "text", "Probing the suspected bug directly:", ""),
    L(5, "user", "injected", "[Request interrupted by user]", ""),
    L(6, "user", "human", "wait, design first", ""),
    L(7, "assistant", "text", "[!reply] Here is the design.", ""),
    L(8, "user", "human", "go", ""),
    L(9, "assistant", "text", "Running the build now.", ""),
    L(10, "user", "tool_result", "[Request interrupted by user for tool use]", ""),
    L(11, "user", "human", "stop", ""),
    L(12, "assistant", "text", "[!reply] Stopped.", ""),
]
check("an interrupted turn files nothing; delivered turns file their last message",
      sorted(transcript.filing_units(turn)), [7, 12])
check("the hook holds for nothing in an interrupted turn",
      [l.n for l in hook.untagged(turn, transcript.filing_units(turn))], [])

# a question put to the user needs no tag
turn = [
    L(1, "user", "human", "which?", ""),
    L(2, "assistant", "text", "asked: Which path?  [patch / recompose]", "", tools=["AskUserQuestion"]),
    L(3, "user", "human", "Your questions have been answered: patch", ""),
    L(4, "assistant", "text", "no tag here", ""),
    L(5, "user", "human", "ok", ""),
]
check("a question asked through the tool is never untagged; a bare message still is",
      [l.n for l in hook.untagged(turn, transcript.filing_units(turn))], [4])

# a task notification ends a turn; the answer before it is the message
turn = [
    L(1, "user", "human", "redesign it?", ""),
    L(2, "assistant", "text", "[!reply] Yes. The proxy turns reads into facts.", ""),
    L(3, "user", "task", "<task-notification>group 3 done</task-notification>", ""),
    L(4, "assistant", "text", "[!info] All four groups are in.", ""),
    L(5, "user", "human", "very nice, write it down", ""),
    L(6, "assistant", "text", "[!info] Written.", ""),
]
check("a task notification ends a turn, so the direct answer is filed",
      sorted(transcript.filing_units(turn)), [2, 4, 6])
check("and the digest shows the answer the user reacted to",
      [l.n for l in digest.select(turn)], [1, 2, 4, 5, 6])

# a hook hold between the answer and the prompt does not push the answer out
turn = [
    L(1, "user", "human", "go", ""),
    L(2, "assistant", "text", "[!reply] Done, here is the result.", ""),
    L(3, "user", "injected", "Stop hook feedback: still open", ""),
    L(4, "assistant", "text", "[!reply] Noted.", ""),
    L(5, "user", "injected", "Stop hook feedback: context", ""),
    L(6, "assistant", "text", "[!reply] Nothing to pin.", ""),
    L(7, "user", "injected", "Stop hook feedback: again", ""),
    L(8, "assistant", "text", "[!reply] Still nothing.", ""),
    L(9, "user", "human", "no! not like that", ""),
    L(10, "assistant", "text", "[!correction] Redone.", ""),
]
check("the last filed message before a prompt is kept whatever the distance",
      2 in {l.n for l in digest.select(turn)}, True)

# questions asked through the tool, and the user's answers, are spoken
dq = Path(tempfile.mkdtemp()) / "q.jsonl"
dq.write_text("\n".join(json.dumps(r) for r in [
    {"type": "user", "origin": {"kind": "human"}, "message": {"role": "user", "content": "can you ask again"}},
    {"type": "assistant", "message": {"role": "assistant", "content": [
        {"type": "text", "text": ""},
        {"type": "tool_use", "id": "tu1", "name": "AskUserQuestion", "input": {"questions": [
            {"question": "Which path?", "options": [{"label": "patch"}, {"label": "recompose"}]}]}}]}},
    {"type": "user", "message": {"role": "user", "content": [
        {"type": "tool_result", "tool_use_id": "tu1", "content": "Your questions have been answered: patch"}]}},
    {"type": "user", "message": {"role": "user", "content": [
        {"type": "tool_result", "tool_use_id": "tu2", "content": "a file"}]}},
]) + "\n")
qs, _ = transcript.read(dq)
check("the question is the agent's spoken text",
      (qs[1].kind, qs[1].spoken, "asked: Which path?  [patch / recompose]" in qs[1].text), ("text", True, True))
check("the answer is the user's own words", (qs[2].kind, qs[2].spoken), ("human", True))
check("an ordinary tool result is still nobody's speech", qs[3].kind, "tool_result")
check("journal user shows the answer", "patch" in digest.users_only(qs), True)

# a prompt recorded twice under one parent is one prompt, and numbering holds
dd = Path(tempfile.mkdtemp()) / "d.jsonl"
dd.write_text("\n".join(json.dumps(r) for r in [
    {"type": "user", "parentUuid": "a", "origin": {"kind": "human"}, "message": {"role": "user", "content": "go"}},
    {"type": "assistant", "parentUuid": "u1", "message": {"role": "assistant", "content": [{"type": "text", "text": "[!reply] ok"}]}},
    {"type": "user", "parentUuid": "b", "origin": {"kind": "human"}, "message": {"role": "user", "content": "dont start yet yhough"}},
    {"type": "user", "parentUuid": "b", "origin": {"kind": "human"}, "message": {"role": "user", "content": "dont start yet though"}},
    {"type": "user", "parentUuid": "b", "origin": {"kind": "human"}, "message": {"role": "user", "content": "dont start yet though. design first"}},
    {"type": "assistant", "parentUuid": "u5", "message": {"role": "assistant", "content": [{"type": "text", "text": "[!reply] design"}]}},
    {"type": "user", "parentUuid": "c", "origin": {"kind": "human"}, "message": {"role": "user", "content": "same words"}},
    {"type": "assistant", "parentUuid": "u7", "message": {"role": "assistant", "content": [{"type": "text", "text": "[!reply] answered"}]}},
    {"type": "user", "parentUuid": "d", "origin": {"kind": "human"}, "message": {"role": "user", "content": "same words"}},
]) + "\n")
ls, _ = transcript.read(dd)
check("the earlier copies are superseded, the last stands",
      [(l.n, l.kind) for l in ls if l.n in (3, 4, 5)], [(3, "superseded"), (4, "superseded"), (5, "human")])
check("numbering is untouched", [l.n for l in ls], list(range(1, 10)))
check("the same words after an answer are a new prompt", (ls[6].kind, ls[8].kind), ("human", "human"))
check("journal user shows one copy", digest.users_only(ls).count("dont start"), 1)

# subagents: every event is closed at the door
d, path = project_with(2)
for event, extra in (("Stop", {}), ("SessionStart", {"source": "startup"}),
                     ("PostToolUse", {"tool_name": "Bash", "tool_response": {"stdout": "x" * 50000}})):
    code, out, err = fire(d, event, path, agent_id="abc", **extra)
    check(f"a subagent's {event} does nothing", (code, out.strip(), err.strip()), (0, "", ""))
check("and writes no runtime file", state.runtime_files(d / ".journal"), [])

# rules: a pin for every track, promote lifts a pin into one
r = fresh()
pins.add(r, "track fact", AT, 300)
took, msg = pins.add(r, "every track obeys this", AT, 300, key=pins.RULES)
check("a rule is written", (took, msg.startswith("ruled 1")), (True, True))
tracks.switch(r, "elsewhere", AT)
check("switching parks the pins but not the rules",
      ([p["fact"] for p in pins.live(r)], [p["fact"] for p in pins.live(r, pins.RULES)]),
      ([], ["every track obeys this"]))
pins.add(r, "another track's fact", AT, 300)
took, msg = pins.promote(r, 1, AT)
check("promote lifts the pin into a rule", (took, "rule 2, from pin 1" in msg), (True, True))
check("the pin is struck and says where it went",
      pins._all(r)[0]["struck"], "promoted to rule 2")
check("the rule carries the claim and remembers its origin",
      (pins.live(r, pins.RULES)[1]["fact"], pins.live(r, pins.RULES)[1]["promoted_from"]),
      ("another track's fact", 1))
took, msg = pins.promote(r, 1, AT)
check("promoting a struck pin is refused", (took, "already struck" in msg), (False, True))
took, msg = pins.strike(r, 1, "repealed", key=pins.RULES)
check("a rule can be struck with a reason", (took, msg.startswith("struck rule 1")), (True, True))
check("rules --all still shows it", "repealed" in pins.render(r, all_of_them=True, key=pins.RULES), True)
tracks.switch(r, "default", AT)
check("back on default: its pin and the one standing rule",
      ([p["fact"] for p in pins.live(r)], [p["fact"] for p in pins.live(r, pins.RULES)]),
      (["track fact"], ["another track's fact"]))
took, msg = pins.add(r, "x" * 400, AT, 300, key=pins.RULES)
check("a rule has the same cap", (took, "300" in msg), (False, True))

# the block hands rules first, on every source, and the gates know the verbs
d, path = project_with(2)
J = str(d / ".journal" / "journal.py")
env = {**os.environ, transcript.SESSION_ENV: "s1"}
subprocess.run([J, "remember", "a track fact"], env=env, capture_output=True)
subprocess.run([J, "rule", "a project rule"], env=env, capture_output=True)
for source in ("startup", "compact"):
    code, out, err = fire(d, "SessionStart", path, source=source)
    ctx = json.loads(out)["hookSpecificOutput"]["additionalContext"]
    check(f"{source}: rules come before pins, under their own header",
          (ctx.find("RULES OF THIS PROJECT") < ctx.find("a project rule") < ctx.find("a track fact")), True)
p = subprocess.run([J, "promote", "1"], env=env, capture_output=True, text=True)
check("cli promote", (p.returncode, "rule 2, from pin 1" in p.stdout), (0, True))
p = subprocess.run([J, "rules"], env=env, capture_output=True, text=True)
check("cli rules lists both", ("a project rule" in p.stdout, "a track fact" in p.stdout), (True, True))
p = subprocess.run([J, "rule", "--strike", "1", "no longer"], env=env, capture_output=True, text=True)
check("cli rule --strike", (p.returncode, "struck rule 1" in p.stdout), (0, True))
got = hook._pin_overflow({"tool_name": "Bash", "tool_input": {"command": f'.journal/journal.py rule "{"x" * 400}"'}}, 300)
check("an over-long rule is denied at the gate", got is not None, True)
code, out, err = fire(d, "PreToolUse", path, agent_id="abc", tool_name="Bash",
                      tool_input={"command": ".journal/journal.py rule \"x\""})
check("a subagent's rule is refused", "from a subagent is refused" in out, True)
code, out, err = fire(d, "PreToolUse", path, agent_id="abc", tool_name="Bash",
                      tool_input={"command": ".journal/journal.py promote 1"})
check("and so is its promote", "from a subagent is refused" in out, True)
runtime = d / ".journal" / "runtime" / "s1.json"
data = json.loads(runtime.read_text()); data["pin_due"] = {"rung": 0.5, "used": 1, "window": 2}
runtime.write_text(json.dumps(data))
subprocess.run([J, "rule", "decided by ruling"], env=env, capture_output=True)
check("a rule counts as the decision at a rung", runtime_of(d, "s1").get("pin_due"), None)

# to-dos: titled files, one track each, closed by the work of the same name
r = fresh()
took, msg = todo.add(r, "default", "convert the remaining widgets", "why: they still read props\nstart in src/View", AT)
check("a to-do is a file under its track", (took, (r / "todo" / "default" / "001-convert-the-remaining-widgets.md").is_file()), (True, True))
took, msg = todo.add(r, "default", "", "", AT)
check("a to-do needs a title", (took, "needs a title" in msg), (False, True))
took, msg = todo.add(r, "default", "Convert the remaining WIDGETS", "", AT)
check("a duplicate open title is refused", (took, "already waiting" in msg), (False, True))
todo.add(r, "default", "write the docs", "", AT)
todo.add(r, "other track", "something else", "", AT)
check("the list is the current track's titles only",
      todo.render(r, "default"), "    1  convert the remaining widgets\n    2  write the docs")
check("another track sees only its own", [t["title"] for t in todo.open_items(r, "other track")], ["something else"])
ok_, body = todo.show(r, "default", 1)
check("the brief is the file's body", ("start in src/View" in body, "to-do 1" in body), (True, True))
t, err = todo.start(r, "default", 1, AT)
check("start marks it", bool(t and t.get("started")), True)
check("ending work with the title closes it", todo.close_titled(r, "default", "convert the remaining widgets", AT), "1")
check("it is gone from the open list", [t["n"] for t in todo.open_items(r, "default")], [2])
check("and its number holds in --all", "1  convert the remaining widgets  done" in todo.render(r, "default", all_of_them=True), True)
took, msg = todo.done(r, "default", 2, "", AT)
check("done wants how", (took, "how it was resolved" in msg), (False, True))
took, msg = todo.done(r, "default", 2, "turned out unnecessary", AT)
check("done with how", (took, "done 2" in msg), (True, True))
took, msg = todo.done(r, "default", 2, "again", AT)
check("done twice is refused", (took, "already done" in msg), (False, True))
check("nothing waiting reads so", todo.render(r, "default"), "Nothing is waiting.")
check("carry names the titles and says it is not an instruction",
      ("something else" in todo.carry(r, "other track"), "not an instruction" in todo.carry(r, "other track")), (True, True))
check("carry is empty with nothing waiting", todo.carry(r, "default"), "")
took, msg = todo.add(r, "a/b: weird  track", "spaced   title", "", AT)
check("hostile track and title names slug safely", took and "spaced title" in msg, True)

# the CLI, and the stop line
d, path = project_with(4, tagged=True)
J = str(d / ".journal" / "journal.py")
env = {**os.environ, transcript.SESSION_ENV: "s1"}
p = subprocess.run([J, "todo", "convert the remaining widgets", "--brief"], env=env,
                   input="brief here\n", capture_output=True, text=True)
check("cli adds with a brief from stdin", (p.returncode, "to-do 1" in p.stdout), (0, True))
p = subprocess.run([J, "todo", "1"], env=env, capture_output=True, text=True)
check("cli shows the brief", "brief here" in p.stdout, True)
p = subprocess.run([J, "todo"], env=env, capture_output=True, text=True)
check("cli lists titles", "1  convert the remaining widgets" in p.stdout, True)
fire(d, "SessionStart", path, source="startup")
code, out, err = fire(d, "Stop", path)
check("a stop with nothing open says what is waiting, as context, not a hold",
      ("to-do(s) waiting" in out, '"decision"' in out, "not an instruction" in out), (True, False, True))
code, out, err = fire(d, "Stop", path)
check("and not again while the list is unchanged", out.strip(), "")
subprocess.run([J, "todo", "second thing"], env=env, capture_output=True, text=True)
code, out, err = fire(d, "Stop", path)
check("a changed list is said once more", "2 to-do(s)" in out, True)
p = subprocess.run([J, "todo", "start", "1"], env=env, capture_output=True, text=True)
check("cli todo start opens the work", (p.returncode, "open: convert the remaining widgets" in p.stdout), (0, True))
code, out, err = fire(d, "Stop", path)
check("with work open the to-do line is not said", "to-do(s) waiting" in out, False)
p = subprocess.run([J, "end", "convert the remaining widgets"], env=env, capture_output=True, text=True)
check("end closes the work and the to-do", "to-do 1 is done with it" in p.stdout, True)
p = subprocess.run([J, "todo", "drop", "2", "no longer wanted"], env=env, capture_output=True, text=True)
check("cli todo drop", (p.returncode, "dropped: no longer wanted" in p.stdout), (0, True))
code, out, err = fire(d, "SessionStart", path, source="startup")
ctx = json.loads(out)["hookSpecificOutput"]["additionalContext"]
check("with none waiting the start block says nothing about to-dos", "TO DO" in ctx, False)
subprocess.run([J, "todo", "third"], env=env, capture_output=True, text=True)
code, out, err = fire(d, "SessionStart", path, source="startup")
ctx = json.loads(out)["hookSpecificOutput"]["additionalContext"]
check("the start block lists what is waiting", ("TO DO on this track" in ctx, "third" in ctx), (True, True))
code, out, err = fire(d, "PreToolUse", path, agent_id="abc", tool_name="Bash",
                      tool_input={"command": ".journal/journal.py todo"})
check("a subagent's todo is refused", "from a subagent is refused" in out, True)

# the installer's pull never carries the record across — to-dos included
import install
r = fresh()
todo.add(r, "default", "mine", "", AT)
(r / "record.json").write_text("{}")
(r / "hook.py").write_text("")
check("to-dos and the record are data the pull leaves behind",
      [str(f) for f in install._package_files(r)], ["hook.py"])

# a subagent's marks are pruned with its transcript, and kept while it lives
d, path = project_with(2)
state.put(d / ".journal", "rules_at", [0.0], stem="agent-old")
sub = path.parent / "s1" / "subagents"; sub.mkdir(parents=True)
(sub / "agent-live.jsonl").write_text("")
state.put(d / ".journal", "rules_at", [0.0], stem="agent-live")
fire(d, "SessionStart", path, source="startup")
check("a subagent's file goes with its transcript, and stays while it exists",
      sorted(s for s, _ in state.runtime_files(d / ".journal")), ["agent-live", "s1"])

# subagents receive the rules: first call, then at their own marks; never pins
d, path = project_with(2)
J = str(d / ".journal" / "journal.py")
env = {**os.environ, transcript.SESSION_ENV: "s1"}
subprocess.run([J, "remember", "a pin of the track"], env=env, capture_output=True)
subprocess.run([J, "rule", "a rule for every track"], env=env, capture_output=True)
(d / ".journal" / "settings.json").write_text(json.dumps({"context_window": 200000}))
call = {"tool_name": "Read", "tool_input": {}, "tool_response": "x"}
code, out, err = fire(d, "PostToolUse", path, agent_id="abc", **call)
ctx = json.loads(out)["hookSpecificOutput"]["additionalContext"]
check("a subagent's first tool call hands it the rules and says whose journal it is",
      ("YOU ARE A SUBAGENT" in ctx, "a rule for every track" in ctx, "a pin of the track" in ctx),
      (True, True, False))
code, out, err = fire(d, "PostToolUse", path, agent_id="abc", **call)
check("the second call is silent", out.strip(), "")
sub = path.parent / "s1" / "subagents"; sub.mkdir(parents=True, exist_ok=True)
(sub / "agent-abc.jsonl").write_text(json.dumps({"type": "assistant", "message": {
    "role": "assistant", "content": [{"type": "text", "text": "working"}],
    "usage": {"input_tokens": 110000}}}) + "\n")
code, out, err = fire(d, "PostToolUse", path, agent_id="abc", **call)
ctx = json.loads(out)["hookSpecificOutput"]["additionalContext"]
check("at 55% of ITS window the rules come back, once for the 25 and 50 marks together",
      ("50% FULL" in ctx, "a rule for every track" in ctx), (True, True))
check("and every mark crossed is recorded", runtime_of(d, "agent-abc")["rules_at"], [0.0, 0.25, 0.5])
code, out, err = fire(d, "PostToolUse", path, agent_id="abc", **call)
check("then silence until the next mark", out.strip(), "")
code, out, err = fire(d, "PostToolUse", path, agent_id="other", tool_name="Bash", tool_input={},
                      tool_response={"stdout": "x" * 90000})
ctx = json.loads(out)["hookSpecificOutput"]["additionalContext"]
check("a subagent is never told about tool cost, only the rules", "CHARACTERS" in ctx, False)
d2, path2 = project_with(2)
code, out, err = fire(d2, "PostToolUse", path2, agent_id="abc", **call)
check("with no rules a subagent hears nothing at all", out.strip(), "")

# the main agent's rung carries the rules again
d, path = project_with(4, tagged=True)
with path.open("a") as fh:
    fh.write(json.dumps({"type": "assistant", "message": {
        "role": "assistant", "content": [{"type": "text", "text": "[!reply] fine"}],
        "usage": {"input_tokens": 120000}}}) + "\n")
(d / ".journal" / "settings.json").write_text(json.dumps({"context_window": 200000}))
subprocess.run([J.replace(J.split("/.journal")[0], str(d)), "rule", "the standing rule"],
               env=env, capture_output=True)
fire(d, "SessionStart", path, source="startup")
code, out, err = fire(d, "Stop", path)
brief, why = held(out)
check("the rung hold carries the rules again",
      ("RULES OF THIS PROJECT" in why, "the standing rule" in why, "far behind" in why), (True, True, True))

# open work: told at start, held only for one's own
d, path = project_with(2, tagged=True)
J = str(d / ".journal" / "journal.py")
env = {**os.environ, transcript.SESSION_ENV: "s1"}
shutil.copy(path, path.with_name("other.jsonl"))  # the other session has a transcript of its own
subprocess.run([J, "start", "somebody else's work"], env={**os.environ, transcript.SESSION_ENV: "other"},
               capture_output=True)
subprocess.run([J, "start", "my work"], env=env, capture_output=True)
code, out, err = fire(d, "SessionStart", path, source="startup")
ctx = json.loads(out)["hookSpecificOutput"]["additionalContext"]
check("SessionStart on startup carries open work from every session",
      ("somebody else's work" in ctx, "my work" in ctx), (True, True))
check("and on startup does not claim a summary", "SUMMARY YOU ARE HOLDING" in ctx, False)
code, out, err = fire(d, "SessionStart", path, source="compact")
ctx = json.loads(out)["hookSpecificOutput"]["additionalContext"]
check("on compact it does", "SUMMARY YOU ARE HOLDING" in ctx, True)
code, out, err = fire(d, "Stop", path)
check("the stop holds only for work THIS transcript opened",
      ("my work" in out, "somebody else's work" in out), (True, False))
code, out, err = fire(d, "Stop", path)
check("and only once", out.strip(), "")

# pins are delivered on every source, with an honest header
d, path = project_with(2)
subprocess.run([J.replace(str(J.split('/.journal')[0]), str(d)), "remember", "a standing fact"],
               env=env, capture_output=True)
for source, head in (("startup", "FACTS THAT STAND ON THIS TRACK"),
                     ("clear", "FACTS THAT STAND ON THIS TRACK"),
                     ("fork", "FACTS THAT STAND ON THIS TRACK"),
                     ("compact", "FACTS THE SUMMARY YOU ARE HOLDING DID NOT KEEP")):
    code, out, err = fire(d, "SessionStart", path, source=source)
    ctx = json.loads(out)["hookSpecificOutput"]["additionalContext"]
    check(f"{source} carries the pin under the right header",
          ("a standing fact" in ctx, head in ctx), (True, True))

# pruning: a runtime file whose transcript is gone is dropped; subagents' are found one level down
d, path = project_with(2)
fire(d, "SessionStart", path, source="startup")
state.put(d / ".journal", "held_at", 5, stem="gone")
sub = path.parent / "s1" / "subagents"
sub.mkdir(parents=True)
(sub / "agent-live.jsonl").write_text("")
state.put(d / ".journal", "biggest_result", 5, stem="agent-live")
fire(d, "SessionStart", path, source="resume")
check("a transcript-less runtime file is pruned; a live subagent's is kept",
      sorted(s for s, _ in state.runtime_files(d / ".journal")), ["agent-live", "s1"])

# the hook survives bad input
d, path = project_with(2)
p = subprocess.run([str(d / ".journal" / "hook.py")], input='{"hook_event_name":"Stop"}',
                   capture_output=True, text=True)
check("a payload with no session: exit 0 and one stderr line",
      (p.returncode, "names no session" in p.stderr), (0, True))
(d / ".journal" / "runtime").mkdir(exist_ok=True)
(d / ".journal" / "runtime" / "s1.json").mkdir()  # a directory where a file should be
code, out, err = fire(d, "Stop", path)
check("a handler that raises: exit 0 and says so", (code, "handler failed" in err), (0, True))

# the CLI knows its own transcript
d, path = project_with(4)
other = path.with_name("zz-newer.jsonl"); shutil.copy(path, other)
os.utime(other, None)
J = str(d / ".journal" / "journal.py")
p = subprocess.run([J, "remember", "cited"], env={**os.environ, transcript.SESSION_ENV: "s1"},
                   capture_output=True, text=True)
rec = json.loads((d / ".journal" / "record.json").read_text())
check("remember cites the session from the environment, not the newest file",
      rec["pins"][-1]["session"], "s1.jsonl")
e = {k: v for k, v in os.environ.items() if k != transcript.SESSION_ENV}
p = subprocess.run([J, "remember", "guessed"], env=e, capture_output=True, text=True)
rec = json.loads((d / ".journal" / "record.json").read_text())
check("without the environment it guesses the newest and SAYS so",
      (rec["pins"][-1].get("guessed"), "guessed" in p.stderr), (True, True))

# verify reads the runtime files and never a baseline
d, path = project_with(2)
import verify
rows, _ = verify.check(d / ".journal")
fired = [ok for name, ok, _ in rows if name.startswith("the hook has ACTUALLY FIRED")]
check("verify: nothing fired before any hook ran", fired, [False])
fire(d, "SessionStart", path, source="startup")
rows, _ = verify.check(d / ".journal")
fired = [ok for name, ok, _ in rows if name.startswith("the hook has ACTUALLY FIRED")]
check("verify: fired once a transcript carries a mark", fired, [True])

print(f"\n{ok} passed, {fail} failed")
sys.exit(1 if fail else 0)
