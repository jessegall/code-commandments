"""Declaring a piece of work — the one thing here that is WRITTEN, and the one that costs.

A TAG IS FREE AND A DECLARATION IS NOT, and the difference is the point. A tag rides on a
message you were sending anyway, so it is spent generously and describes what that message
carried. Starting work is a COMMITMENT: it says a thing is now in flight and somebody is
answerable for finishing it. Making that cost a deliberate command is what makes it done
with thought — a free one would be sprayed across every message that mentions doing
something, and then `open` would list forty things nobody is holding.

It is also the one fact a transcript cannot yield. Everything else here is derived on
demand from what was said; whether a piece of work is STILL OPEN is not in anything that
was said — it is the absence of a later sentence, and an absence is not readable. So it is
state, it is small, and it is written down.
"""
from __future__ import annotations

from pathlib import Path

import state

KEY = "work"


def _all(root: Path) -> list[dict]:
    got = state.get(root, KEY, [])
    return got if isinstance(got, list) else []


def open_work(root: Path) -> list[dict]:
    return [w for w in _all(root) if not w.get("ended")]


def start(root: Path, subject: str, at: str, where: dict | None = None) -> tuple[bool, str]:
    """Declare work. Refuses a duplicate rather than opening a second of the same thing.

    `where` records which transcript opened it. The journal is shared, so work opened in
    one session is still open in the next — and the next session is TOLD about it at its
    start, not HELD for it at its first stop. A hold is for a commitment this agent made;
    the stop hook uses the recorded transcript to tell the two apart.
    """
    subject = " ".join(subject.split())
    if not subject:
        return False, "start what? give it a name you will say again to close it"
    with state.locked(root):
        for w in open_work(root):
            if w["subject"].lower() == subject.lower():
                return False, f"already open since {w['at'][:19]} — nothing to do"
        items = _all(root)
        items.append({"subject": subject, "at": at, "ended": None, **(where or {})})
        state.put(root, KEY, items)
    return True, f"open: {subject}"


def end(root: Path, subject: str, at: str) -> tuple[bool, str]:
    """Close it by saying the same words.

    A close that matched nothing is REFUSED and lists what is open. Silently accepting it
    would let the agent believe it had closed work that is still standing — and an open
    piece of work nobody knows about is exactly what this exists to prevent.
    """
    subject = " ".join(subject.split()).lower()
    with state.locked(root):
        items = _all(root)
        for w in items:
            if not w.get("ended") and w["subject"].lower() == subject:
                w["ended"] = at
                state.put(root, KEY, items)
                return True, f"closed: {w['subject']}"
    still = open_work(root)
    if not still:
        return False, "nothing is open"
    return False, "that closes nothing. Open:\n" + "\n".join(
        f"  {w['subject']}" for w in still
    )


def note(root: Path, text: str, at: str, on: str | None = None) -> tuple[bool, str]:
    """File progress AGAINST a piece of work. The thing `[!update]` was pretending to be.

    It is a command and not a tag for the reason `start` is: it is about the WORK, not
    about the message carrying it. A tag describes what you just said and can therefore
    never be wrong; an update makes a claim about something outside itself, and the moment
    that claim can be wrong it stops being free. This one costs a command, and that cost is
    the thought.

    It REFUSES with nothing open, and refuses to guess between several. Attaching a note to
    the wrong scope is worse than not filing it: the note reads as true under a heading it
    was never about, and nothing about it looks broken afterwards.
    """
    text = " ".join((text or "").split())
    if not text:
        return False, "update what? say what moved, in one line"
    with state.locked(root):
        return _note(root, text, at, on)


def _note(root: Path, text: str, at: str, on: str | None) -> tuple[bool, str]:
    standing = open_work(root)
    if not standing:
        return False, (
            "nothing is open, so there is no work for this to be about.\n"
            "  journal start \"<the work>\"   then update it"
        )
    if on:
        want = " ".join(on.split()).lower()
        match = [w for w in standing if w["subject"].lower() == want]
        if not match:
            return False, "--on matches nothing open. Open:\n" + "\n".join(
                f"  {w['subject']}" for w in standing
            )
        target = match[0]
    elif len(standing) > 1:
        return False, (
            "several pieces of work are open, so this would have to guess which one "
            "moved. Name it:\n"
            + "\n".join(f"  journal update \"...\" --on=\"{w['subject']}\"" for w in standing)
        )
    else:
        target = standing[0]

    items = _all(root)
    for w in items:
        if w is target or (not w.get("ended") and w["subject"] == target["subject"]):
            w.setdefault("notes", []).append({"at": at, "text": text})
            state.put(root, KEY, items)
            n = len(w["notes"])
            return True, f"{target['subject']}: {n} update(s) filed"
    return False, "that work vanished between reading it and writing to it"
