#!/usr/bin/env python3
"""journal — a session survives its own compaction.

A compaction keeps what was DONE and loses what was DECIDED. The transcript on disk lost
nothing. This is the index that gets you back to it.

    journal                 the conversation since the last compaction
    journal --back=N        N compactions back; --back=1 is what the last summary REPLACED
    journal user            only the user's own words, in full
    journal open            work declared and never closed
    journal search <term>   every line mentioning it, and who said it
    journal remember "<fact>" [--supersedes=N]   a fact that must survive a compaction
    journal nothing "<why>"  after a context warning: nothing here needs pinning, and why
    journal rule "<ruling>"  a pin for EVERY track — what the project decided, not one line of work
    journal rules [--all]    every rule, numbered; `rules N --full` reads around one
    journal rule --strike N "<why>"   repeal a rule that stopped being true
    journal promote N        lift pin N into a rule; the pin is struck and says where it went
    journal todo "<title>" [--brief]   delayed work, on this track; --brief reads a longer brief from stdin
    journal todo [--all]     the titles, numbered
    journal todo N           the whole brief
    journal todo start N     open work with that title — `end` then closes both
    journal todo done N "<how>"   resolved without starting it
    journal todo drop N "<why>"   abandoned, on the record
    journal pins [--all]    every pin, numbered — the number is what --supersedes takes
    journal pins N --full   the conversation around where pin N was written
    journal strike N "<why>" retire a pin that stopped being true, no replacement needed
    journal start "<what>"  declare work — a commitment, which is why it costs a command
    journal update "<what moved>" [--on="<work>"]   progress on the open work
    journal end "<what>"    the same words, to close it
    journal carry           exactly what a compaction will hand back — nothing is written
    journal tracks          every track of work, current one marked
    journal switch "<name>" park this one and pick up that one; --back for the last
    journal verify          is any of this in force? wired is not the same as fired
    journal settings        every setting, its value, and where it came from
"""
from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

import digest
import settings as settings_mod
import context
import pins
import tags
import todo
import tracks
import transcript
import verify
import work


def root() -> Path:
    return Path(__file__).resolve().parent


def project() -> Path:
    return root().parent


def _load(back: int = 0):
    conf, problems = settings_mod.load(root())
    for p in problems:
        print(f"  ! {p}", file=sys.stderr)
    digest.CONTEXT = conf["context_messages"]
    path = _transcript()
    lines, boundaries = transcript.read(path)
    return conf, lines, boundaries, transcript.since(lines, boundaries, back), path


def _transcript() -> Path:
    """The transcript this command is about: this session's, or a labelled guess.

    Every Bash call made from inside a session carries the session id in its environment,
    so the CLI is not blind. It reads the newest file by mtime only for a person at a bare
    terminal, and then it SAYS it guessed — with two terminals open the guess is the other
    one, and a `search` that quietly answered from the wrong conversation is the confident
    falsehood this tool exists to prevent.
    """
    got = _resolved()
    if got is None:
        print("No transcript for this project yet.", file=sys.stderr)
        raise SystemExit(1)
    return got[0]


def _resolved() -> tuple[Path, bool] | None:
    got = transcript.session_transcript(project())
    if got and got[1]:
        print(f"  (guessed: newest transcript, {got[0].name} — {transcript.SESSION_ENV} is "
              "not set)", file=sys.stderr)
    return got


def cmd_read(back: int) -> int:
    conf, lines, boundaries, seg, path = _load(back)
    n = len(boundaries)
    if back > n:
        # SAY IT RATHER THAN CLAMP. `since` shows the oldest stretch for any N past the
        # first compaction; labelling that as "N back" is an index that lies.
        print(f"  ! only {n} compaction(s) in this session; showing the oldest stretch",
              file=sys.stderr)
        back = n
    where = "since the last compaction" if back == 0 else f"the stretch {back} summary/ies back REPLACED"
    print(f"# {where} — {len(seg)} lines, {n} compaction(s) in this session\n")
    body = digest.render(seg)
    print(body if body.strip() else "  (nothing was said in this stretch)")
    if back == 0 and n:
        print(f"\n  Read `journal --back=1` next: that is precisely what the last summary dropped.")
    return 0


def cmd_user(back: int) -> int:
    _, _, _, seg, _ = _load(back)
    body = digest.users_only(seg)
    print("# the user's own words, in full\n")
    print(body if body.strip() else "  (the user said nothing in this stretch)")
    return 0


def cmd_open() -> int:
    standing = work.open_work(root())
    if not standing:
        print("Nothing is open.")
        return 0
    print("# declared and never closed\n")
    for w in standing:
        print(f"  {w['at'][:19]}  {w['subject']}")
        # THE NOTES ARE THE POINT OF `open`, not decoration. A subject alone says a thing
        # is in flight; the notes say where it got to, which is what a reader on the far
        # side of a compaction actually needs before they touch it.
        for note in w.get("notes", []):
            print(f"      {note['at'][11:19]}  {note['text']}")
    print("\nClose each with `journal end \"<the same words>\"`, or say where it got to.")
    return 0


def _now() -> str:
    from datetime import datetime, timezone
    return datetime.now(timezone.utc).isoformat(timespec="seconds")


def cmd_start(subject: str) -> int:
    ok, msg = work.start(root(), subject, _now(), _where())
    print(msg if ok else f"  ! {msg}", file=sys.stdout if ok else sys.stderr)
    return 0 if ok else 1


def cmd_end(subject: str) -> int:
    """Close the work, and ask the one question that is only answerable now.

    THE MOMENT WORK CLOSES IS THE MOMENT YOU KNOW WHAT IT TAUGHT. Before it, you cannot
    say; long after, you no longer remember there was anything to say. Pins were coming out
    sparse — three in a full day of work — and the only prompt to write one fired at 75%
    context, which is late and is about the compaction rather than about the work.

    It ASKS, it does not hold. A gate here would be a third rule, and this is a question
    with a legitimate answer of "nothing" — most work teaches nothing that outlives it.
    """
    ok, msg = work.end(root(), subject, _now())
    print(msg if ok else f"  ! {msg}", file=sys.stdout if ok else sys.stderr)
    if ok:
        closed = todo.close_titled(root(), tracks.current(root()), subject, _now())
        if closed:
            print(f"  to-do {closed} is done with it.")
        print('  did that teach anything a later reader would get wrong without?\n'
              '    journal remember "<the claim, in one line>"   (or nothing, which is fine)')
    return 0 if ok else 1


def cmd_update(text: str, on: str | None) -> int:
    ok, msg = work.note(root(), text, _now(), on)
    print(msg if ok else f"  ! {msg}", file=sys.stdout if ok else sys.stderr)
    return 0 if ok else 1


def cmd_search(term: str) -> int:
    _, lines, _, _, _ = _load(0)
    needle = term.lower()
    hits = [l for l in lines if l.spoken and needle in (l.text or "").lower()]
    print(f"# {len(hits)} line(s) mentioning {term!r}\n")
    for l in hits:
        who = "USER" if l.kind == "human" else "    "
        body = " ".join(tags.strip(l.text).split())
        i = body.lower().find(needle)
        lo = max(0, i - 120)
        print(f"{l.n:>5}  {who}  …{body[lo:i + 200]}…")
    return 0


def _where() -> dict:
    """The transcript position this pin is being written at, so it can be read around later.

    Recorded at WRITE time and never recomputed: the newest session changes, and a pin that
    silently re-points at a different conversation is an index that lies.
    """
    got = _resolved()
    if got is None:
        return {}
    path, guessed = got
    lines, _ = transcript.read(path)
    where = {"line": lines[-1].n if lines else 0, "session": path.name}
    if guessed:
        where["guessed"] = True  # so `pins <n> --full` can say the citation may be off
    return where


def cmd_remember(fact: str, supersedes: int | None) -> int:
    conf, _ = settings_mod.load(root())
    ok, msg = pins.add(root(), fact, _now(), conf["pin_max_chars"], supersedes, _where())
    print(msg if ok else f"  ! {msg}", file=sys.stdout if ok else sys.stderr)
    if ok:
        _decided("pinned")
    return 0 if ok else 1


def _stem() -> str | None:
    got = transcript.session_transcript(project())
    return got[0].stem if got else None


def _decided(how: str) -> bool:
    """Lift the gate a context rung lowered. True if one was standing."""
    import state
    stem = _stem()
    due = state.get(root(), "pin_due", None, stem=stem) if stem else None
    if not due:
        return False
    state.put(root(), "pin_due", None, stem=stem)
    state.put(root(), "pin_decided", {**due, "how": how, "at": _now()}, stem=stem)
    return True


def cmd_nothing(why: str) -> int:
    """Decline to pin, on the record. The way through the rung gate that is not a pin.

    IT WANTS A REASON, and the reason is the whole point: it is the thought the gate
    exists to force, and it lands in the transcript where a later reader can argue with
    it. A bare "nothing" would be the nudge being clicked through, which is what the gate
    replaced.
    """
    why = " ".join((why or "").split())
    if not why:
        print('nothing wants a reason: journal nothing "<why nothing here needs pinning>"',
              file=sys.stderr)
        return 1
    if _decided("declined: " + why):
        print(f"noted — nothing pinned at this rung, because: {why}")
        return 0
    print("no pin is due — no context warning is waiting on a decision", file=sys.stderr)
    return 1


def cmd_rule(fact: str, strike_n: int | None, why: str) -> int:
    conf, _ = settings_mod.load(root())
    if strike_n is not None:
        ok, msg = pins.strike(root(), strike_n, why, key=pins.RULES)
    else:
        ok, msg = pins.add(root(), fact, _now(), conf["pin_max_chars"], None, _where(),
                           key=pins.RULES)
        if ok:
            _decided("ruled")
    print(msg if ok else f"  ! {msg}", file=sys.stdout if ok else sys.stderr)
    return 0 if ok else 1


def cmd_rules(all_of_them: bool, n: int | None, full: bool) -> int:
    if n is not None and full:
        conf, _ = settings_mod.load(root())
        ok, body = pins.around(root(), n, project(), conf["pin_context"], key=pins.RULES)
        print(body if ok else f"  ! {body}", file=sys.stdout if ok else sys.stderr)
        return 0 if ok else 1
    live = len(pins.live(root(), pins.RULES))
    struck = len(pins._all(root(), pins.RULES)) - live
    print(f"# rules of this project — {live} in force, on every track"
          + (f", {struck} struck" + ("" if all_of_them else " (--all shows them)") if struck else "")
          + "\n")
    print(pins.render(root(), all_of_them=all_of_them, key=pins.RULES))
    print("\n  Handed first to every session and to every subagent. "
          "`journal rules <n> --full` reads the conversation\n"
          "  around one; `journal rule --strike <n> \"<why>\"` repeals one.")
    return 0


def cmd_promote(n: int) -> int:
    ok, msg = pins.promote(root(), n, _now(), _where())
    print(msg if ok else f"  ! {msg}", file=sys.stdout if ok else sys.stderr)
    return 0 if ok else 1


def cmd_todo(rest: list[str], all_of_them: bool, brief: bool = False) -> int:
    here = tracks.current(root())
    if not rest:
        waiting = todo.open_items(root(), here)
        print(f"# to-do on track `{here}` — {len(waiting)} waiting\n")
        print(todo.render(root(), here, all_of_them=all_of_them))
        print("\n  `journal todo <n>` reads the brief; `journal todo start <n>` picks one up; "
              "`journal todo \"<title>\"` adds one.")
        return 0
    verb = rest[0]
    if verb in ("start", "done", "drop"):
        if len(rest) < 2 or not rest[1].isdigit():
            print(f'todo {verb} wants a number: journal todo {verb} 3' + (
                ' "<how>"' if verb != "start" else ""), file=sys.stderr)
            return 1
        n = int(rest[1])
        if verb == "start":
            t, err = todo.start(root(), here, n, _now())
            if t is None:
                print(f"  ! {err}", file=sys.stderr)
                return 1
            ok, msg = work.start(root(), t["title"], _now(), _where())
            print(msg if ok else f"  ! {msg}", file=sys.stdout if ok else sys.stderr)
            if ok:
                print(f"  to-do {n} is started; `journal end \"{t['title']}\"` closes both.")
            return 0 if ok else 1
        why = " ".join(rest[2:])
        if verb == "drop":
            if not why.strip():
                print('say why: journal todo drop <n> "<why it is abandoned>"', file=sys.stderr)
                return 1
            why = "dropped: " + why
        ok, msg = todo.done(root(), here, n, why, _now())
        print(msg if ok else f"  ! {msg}", file=sys.stdout if ok else sys.stderr)
        return 0 if ok else 1
    if verb.isdigit():
        ok, body = todo.show(root(), here, int(verb))
        print(body if ok else f"  ! {body}", file=sys.stdout if ok else sys.stderr)
        return 0 if ok else 1
    # adding: the title is the words; the brief comes on stdin ONLY when asked for with
    # --brief. Reading stdin whenever it is not a terminal hung under a test runner whose
    # stdin never closed, and a command that can hang is worse than one that asks.
    title = " ".join(rest)
    body = sys.stdin.read() if brief else ""
    ok, msg = todo.add(root(), here, title, body, _now(), _where())
    print(msg if ok else f"  ! {msg}", file=sys.stdout if ok else sys.stderr)
    return 0 if ok else 1


def cmd_carry(fresh: bool) -> int:
    """Show the block a compaction hands back, without being a compaction.

    Worth a command of its own because it is the one output nobody could see: assembled
    inside a hook, delivered into a context the user does not read, and previously visible
    only by piping a fake payload into `hook.py` — which wrote state, so looking at it
    changed it.
    """
    import hook
    print(hook.carried("startup" if fresh else "compact"))
    return 0


def cmd_tracks() -> int:
    rows = tracks.listing(root())
    print("# tracks — the current one is where pins and work are going\n")
    for t in rows:
        mark = "*" if t["current"] else " "
        when = f"   parked {t['at'][:16]}" if t["at"] else ""
        print(f" {mark} {t['name']:<28} {t['pins']} pin(s), {t['open']} open{when}")
    print('\nSwitch with `journal switch "<name>"`. Nothing is ever closed by switching.')
    return 0


def cmd_switch(name: str, go_back: bool) -> int:
    ok, msg = (tracks.back(root(), _now()) if go_back
               else tracks.switch(root(), name, _now()))
    print(msg if ok else f"  ! {msg}", file=sys.stdout if ok else sys.stderr)
    return 0 if ok else 1


def cmd_strike(n: int, why: str) -> int:
    ok, msg = pins.strike(root(), n, why)
    print(msg if ok else f"  ! {msg}", file=sys.stdout if ok else sys.stderr)
    return 0 if ok else 1


def cmd_pin_full(n: int) -> int:
    conf, _ = settings_mod.load(root())
    ok, body = pins.around(root(), n, project(), conf["pin_context"])
    print(body if ok else f"  ! {body}", file=sys.stdout if ok else sys.stderr)
    return 0 if ok else 1


def cmd_pins(all_of_them: bool) -> int:
    conf, _ = settings_mod.load(root())
    n = len(pins.live(root()))
    total = len(pins._all(root()))
    struck = total - n
    print(f"# pins on track `{tracks.current(root())}` — {n} standing"
          + (f", {struck} struck" + ("" if all_of_them else " (--all shows them)") if struck else "")
          + "\n")
    print(pins.render(root(), all_of_them=all_of_them))
    print("\n  Handed to every session on this track. "
          "`journal pins <n> --full` reads the conversation around one;\n"
          "  `journal promote <n>` makes one a rule for every track.")
    got = transcript.session_transcript(project())
    if got:
        read = context.pressure(got[0], conf["context_window"])
        if read and read[3]:
            print(f"  Context {read[0]:.0%} full ({read[1]:,} of {read[2]:,}).")
        elif read:
            print(f"  Context: {read[1]:,} tokens; window unknown — the ladder is silent. "
                  f"Set context_window in .journal/settings.json.")
    return 0


# THE SECRETARY LIVED HERE, and it is gone because it was never once used. `secretary_id`
# read NEVER SET across every compaction this project has had, including the ones it was
# designed for — what got reached for instead was `--back=1` and the injected block.
#
# It was the only part of this system that had to be CALLED: spawn an agent, keep its
# handle, dispatch a brief, paste the reply back. Four remembered steps at the exact moment
# a session has just lost its memory, against a rule written down in `tags.py` — a mechanism
# that must be called to prevent a loss is a discipline, and this one is not. The one place
# that rule was broken is the one place nothing ever fired.
#
# It also summarised a source that had lost nothing. The transcript is complete on disk and
# `--back=1` reads it; briefing an agent on the digest of it added a second lossy step to
# recover from the first. And `journal secretary <id>` existed only to remember the handle
# across a compaction — a feature whose sole job was surviving the thing its own feature was
# built to survive, which is the design arguing with itself.


def cmd_settings() -> int:
    conf, problems = settings_mod.load(root())
    f = root() / settings_mod.PATH
    print(f"# settings — {f if f.is_file() else 'no file, every default in force'}\n")
    for key, default in settings_mod.DEFAULTS.items():
        mark = " " if conf[key] == default else "*"
        print(f" {mark} {key:<22} {str(conf[key]):<10} (default {default})")
    if any(conf[k] != settings_mod.DEFAULTS[k] for k in settings_mod.DEFAULTS):
        print("\n  * = set in settings.json")
    for p in problems:
        print(f"\n  ! {p}")
    return 1 if problems else 0


def main(argv: list[str]) -> int:
    back = 0
    supersedes = None
    all_of_them = False
    go_back = False
    fresh = False
    full = False
    on = None
    strike_n = None
    brief = False
    rest = []
    for a in argv:
        if a.startswith("--back="):
            try:
                back = int(a.split("=", 1)[1])
            except ValueError:
                print(f"--back wants a number, got {a.split('=', 1)[1]!r}", file=sys.stderr)
                return 1
        elif a.startswith("--supersedes="):
            try:
                supersedes = int(a.split("=", 1)[1])
            except ValueError:
                print("--supersedes wants a pin number; `journal pins` numbers them", file=sys.stderr)
                return 1
        elif a.startswith("--on="):
            on = a.split("=", 1)[1]
        elif a == "--strike":
            strike_n = -1  # the number follows as the next word
        elif a == "--brief":
            brief = True
        elif a == "--full":
            full = True
        elif a == "--fresh":
            fresh = True
        elif a == "--back":
            go_back = True
        elif a == "--all":
            all_of_them = True
        else:
            rest.append(a)
    verb = rest[0] if rest else ""
    if verb in ("-h", "--help", "help"):
        print(__doc__)
        return 0
    if verb == "user":
        return cmd_user(back)
    if verb == "open":
        return cmd_open()
    if verb == "search":
        if len(rest) < 2:
            print("search wants a term", file=sys.stderr)
            return 1
        return cmd_search(" ".join(rest[1:]))
    if verb == "remember":
        if len(rest) < 2:
            print("remember wants the fact, in one line", file=sys.stderr)
            return 1
        return cmd_remember(" ".join(rest[1:]), supersedes)
    if verb == "nothing":
        return cmd_nothing(" ".join(rest[1:]))
    if verb == "rule":
        if strike_n is not None:
            if len(rest) < 3:
                print('rule --strike wants a number and why: journal rule --strike 2 "<why>"',
                      file=sys.stderr)
                return 1
            try:
                return cmd_rule("", int(rest[1]), " ".join(rest[2:]))
            except ValueError:
                print(f"rule --strike wants a NUMBER, got {rest[1]!r}. `journal rules` numbers them.",
                      file=sys.stderr)
                return 1
        if len(rest) < 2:
            print("rule wants the ruling, in one line", file=sys.stderr)
            return 1
        return cmd_rule(" ".join(rest[1:]), None, "")
    if verb == "rules":
        n = None
        if len(rest) > 1:
            try:
                n = int(rest[1])
            except ValueError:
                print(f"rules wants a NUMBER with --full, got {rest[1]!r}", file=sys.stderr)
                return 1
        return cmd_rules(all_of_them, n, full)
    if verb == "promote":
        if len(rest) < 2:
            print("promote wants a pin number: journal promote 3", file=sys.stderr)
            return 1
        try:
            return cmd_promote(int(rest[1]))
        except ValueError:
            print(f"promote wants a pin NUMBER, got {rest[1]!r}. `journal pins` numbers them.",
                  file=sys.stderr)
            return 1
    if verb == "todo":
        return cmd_todo(rest[1:], all_of_them, brief)
    if verb == "carry":
        return cmd_carry(fresh)
    if verb == "tracks":
        return cmd_tracks()
    if verb == "switch":
        return cmd_switch(" ".join(rest[1:]), go_back)
    if verb == "strike":
        if len(rest) < 3:
            print('strike wants a pin number and why: journal strike 6 "<why>"',
                  file=sys.stderr)
            return 1
        try:
            n = int(rest[1])
        except ValueError:
            print(f"strike wants a pin NUMBER, got {rest[1]!r}. `journal pins` numbers them.",
                  file=sys.stderr)
            return 1
        return cmd_strike(n, " ".join(rest[2:]))
    if verb == "pins":
        if len(rest) > 1 and full:
            try:
                return cmd_pin_full(int(rest[1]))
            except ValueError:
                print(f"pins wants a NUMBER with --full, got {rest[1]!r}", file=sys.stderr)
                return 1
        return cmd_pins(all_of_them)
    if verb == "update":
        if len(rest) < 2:
            print('update wants the words: journal update "<what moved>"', file=sys.stderr)
            return 1
        return cmd_update(" ".join(rest[1:]), on)
    if verb in ("start", "end"):
        if len(rest) < 2:
            print(f"{verb} wants the words that name the work", file=sys.stderr)
            return 1
        subject = " ".join(rest[1:])
        return cmd_start(subject) if verb == "start" else cmd_end(subject)
    if verb == "verify":
        body, ok = verify.render(root())
        print(body)
        return 0 if ok else 1
    if verb == "settings":
        return cmd_settings()
    if verb:
        print(f"No such command: {verb}\n", file=sys.stderr)
        print(__doc__, file=sys.stderr)
        return 1
    return cmd_read(back)


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
