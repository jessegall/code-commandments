#!/usr/bin/env python3
"""Everything switching a track must never get wrong.

    .journal/test_tracks.py

RUN IT BEFORE PUBLISHING A CHANGE TO ANOTHER PROJECT. Switching is the one operation here
that moves somebody's whole record from one place to another, and it has already gone
wrong once: renaming the key `threads` to `tracks` silently changed which FILE the data
belonged to, because `state._file()` routes by name, and a fully parked track vanished
from the listing while sitting untouched on disk. Nothing was lost only because nothing
here deletes.

Every test runs against a throwaway directory. It never touches the real record.
"""
import json, shutil, subprocess, sys, tempfile
from pathlib import Path

SRC = Path(__file__).resolve().parent
sys.path.insert(0, str(SRC))
import state, pins, work, tracks  # noqa: E402

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
    d = Path(tempfile.mkdtemp())
    return d


def live(root):
    return ([p["fact"] for p in pins.live(root)],
            [w["subject"] for w in work.open_work(root)])


# ---------------------------------------------------------------- the basics
r = fresh()
check("defaults to `default` with no file", tracks.current(r), "default")
check("listing shows one empty track", tracks.listing(r),
      [{"name": "default", "current": True, "pins": 0, "open": 0, "at": ""}])

pins.add(r, "fact A", AT, 12)
work.start(r, "work A", AT)
work.note(r, "A moved", AT)
check("default holds its own", live(r), (["fact A"], ["work A"]))

# ---------------------------------------------------------------- switching
took, msg = tracks.switch(r, "beta", AT)
check("switch reports success", took, True)
check("new track is empty", live(r), ([], []))
check("current is beta", tracks.current(r), "beta")

pins.add(r, "fact B", AT, 12)
work.start(r, "work B", AT)
check("beta holds its own", live(r), (["fact B"], ["work B"]))

took, _ = tracks.switch(r, "default", AT)
check("switching back restores pins and work exactly", live(r), (["fact A"], ["work A"]))
check("the note survived the round trip",
      [n["text"] for n in state.get(r, "work")[0]["notes"]], ["A moved"])
check("beta is parked, not lost",
      sorted(t["name"] for t in tracks.listing(r)), ["beta", "default"])

tracks.switch(r, "beta", AT)
check("beta is unchanged after being parked", live(r), (["fact B"], ["work B"]))

# ---------------------------------------------------------------- --back
tracks.switch(r, "default", AT)
took, _ = tracks.back(r, AT)
check("--back returns to beta", tracks.current(r), "beta")
check("--back carries beta's contents", live(r), (["fact B"], ["work B"]))

# ---------------------------------------------------------------- refusals
took, msg = tracks.switch(r, "beta", AT)
check("refuses switching to where you are", (took, "already on" in msg), (False, True))
took, msg = tracks.switch(r, "   ", AT)
check("refuses an empty name", (took, "switch to what" in msg), (False, True))
check("a refused switch changed nothing", live(r), (["fact B"], ["work B"]))

r2 = fresh()
took, msg = tracks.back(r2, AT)
check("refuses --back with nowhere to go", (took, "no track" in msg), (False, True))

# ------------------------------------------------- struck pins and closed work
r3 = fresh()
pins.add(r3, "keep me", AT, 12)
pins.add(r3, "strike me", AT, 12)
pins.strike(r3, 2, "expired")
work.start(r3, "done thing", AT)
work.end(r3, "done thing", AT)
before = json.dumps(state.get(r3, "pins")) + json.dumps(state.get(r3, "work"))
tracks.switch(r3, "elsewhere", AT)
tracks.switch(r3, "default", AT)
after = json.dumps(state.get(r3, "pins")) + json.dumps(state.get(r3, "work"))
check("struck pins and ended work survive byte for byte", after, before)
check("a struck pin is still struck after a round trip",
      [p["fact"] for p in pins.live(r3)], ["keep me"])

# --------------------------------------------- where the data actually lives
# THE RENAME BUG: `state._file` routes by key NAME, so a track's data must sit in
# record.json — the half that belongs to the project — and never in runtime state.
r4 = fresh()
pins.add(r4, "in the record", AT, 12)
tracks.switch(r4, "other", AT)
rec = json.loads((r4 / "record.json").read_text())
check("parked tracks live in record.json", "tracks" in rec, True)
check("current lives in record.json", rec.get("current"), "other")
check("no runtime file was written by a record operation", (r4 / "runtime").exists(), False)

# ----------------------------------------------------------- many round trips
r5 = fresh()
for i in range(6):
    tracks.switch(r5, f"t{i}", AT)
    pins.add(r5, f"pin {i}", AT, 12)
for i in range(6):
    tracks.switch(r5, f"t{i}", AT)
    check(f"t{i} kept exactly its own pin", [p["fact"] for p in pins.live(r5)], [f"pin {i}"])
check("seven tracks exist after six switches", len(tracks.listing(r5)), 7)

# ------------------------------------------------------- the CLI, end to end
d = Path(tempfile.mkdtemp()) / "proj"
(d / ".claude").mkdir(parents=True)
# THE REAL RUNTIME NEVER TRAVELS. A copied `runtime/` would make the hook under test
# inherit marks it did not write, and `verify` would count them as evidence.
shutil.copytree(SRC, d / ".journal",
                ignore=shutil.ignore_patterns("runtime", "state.json*", "record.json*",
                                              "__pycache__"))
J = str(d / ".journal" / "journal.py")


def run_cli(*args):
    p = subprocess.run([J, *args], capture_output=True, text=True)
    return p.returncode, p.stdout + p.stderr


run_cli("remember", "cli fact")
run_cli("start", "cli work")
code, out = run_cli("switch", "second")
check("cli switch succeeds", code, 0)
check("cli reports the park", "default is parked" in out, True)
code, out = run_cli("pins")
check("cli new track has no pins", "Nothing is pinned" in out, True)
run_cli("switch", "--back")
code, out = run_cli("pins")
check("cli --back restores the pin", "cli fact" in out, True)
code, out = run_cli("open")
check("cli --back restores the work", "cli work" in out, True)
code, out = run_cli("tracks")
check("cli lists both, current marked", (" * default" in out, "second" in out), (True, True))

# the hook names the right track and carries only its pins
hook = str(d / ".journal" / "hook.py")
START = json.dumps({"hook_event_name": "SessionStart", "source": "compact",
                    "session_id": "s1", "transcript_path": str(d / "s1.jsonl")})
p = subprocess.run([hook], input=START, capture_output=True, text=True)
ctx = json.loads(p.stdout)["hookSpecificOutput"]["additionalContext"]
check("SessionStart names the current track", "on track `default`" in ctx, True)
check("SessionStart carries this track's pin", "cli fact" in ctx, True)
run_cli("switch", "second")
p = subprocess.run([hook], input=START, capture_output=True, text=True)
ctx = json.loads(p.stdout)["hookSpecificOutput"]["additionalContext"]
check("SessionStart on another track names it", "on track `second`" in ctx, True)
check("and does NOT leak the other track's pin", "cli fact" in ctx, False)

# ------------------------------------------------------- hostile input
r6 = fresh()
pins.add(r6, "safe", AT, 12)
for name in ("a/b", "..", "  spaced   out  ", "emoji 🎧", "x" * 300, "-–quote\"'"):
    took, _ = tracks.switch(r6, name, AT)
    check(f"switch survives {name[:14]!r}", took, True)
    check(f"{name[:14]!r} starts empty", pins.live(r6), [])
check("no file was created per track name",
      sorted(f.name for f in r6.iterdir()), ["record.json", "record.json.lock"])
tracks.switch(r6, "default", AT)
check("default still intact after hostile names", [p["fact"] for p in pins.live(r6)], ["safe"])
check("a name is normalised, not duplicated",
      len([t for t in tracks.listing(r6) if t["name"] == "spaced out"]), 1)

r7 = fresh()
(r7 / "record.json").write_text("{ this is not json")
check("a corrupt record reads as empty rather than crashing", tracks.current(r7), "default")
took, _ = tracks.switch(r7, "anything", AT)
check("and switching still works on top of it", took, True)

r8 = fresh()
(r8 / "record.json").write_text(json.dumps({"tracks": "not a dict", "current": None}))
check("a wrong-typed tracks blob is ignored", tracks.listing(r8)[0]["name"], "default")

# PINS ARE NOT RATIONED BY COUNT ANY MORE — the limit moved to LENGTH — so what a track
# owes is isolation, not a budget. This test used to assert the cap refused a thirteenth
# pin; it now asserts that a track keeps exactly its own pins however many there are.
r9 = fresh()
for i in range(30):
    pins.add(r9, f"p{i}", AT, 140)
check("thirty pins stand on one track", len(pins.live(r9)), 30)
took, msg = pins.add(r9, "x" * 200, AT, 140)
check("a pin longer than the limit is refused", (took, "140" in msg), (False, True))
tracks.switch(r9, "empty one", AT)
check("another track sees none of them", pins.live(r9), [])
took, _ = pins.add(r9, "room here", AT, 140)
check("and can still be pinned to", took, True)
tracks.switch(r9, "default", AT)
check("the thirty are untouched", len(pins.live(r9)), 30)

print(f"\n{ok} passed, {fail} failed")
sys.exit(1 if fail else 0)
