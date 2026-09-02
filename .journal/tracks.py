"""One journal, several tracks of work — and none of them a Claude Code session.

A TRACK IS NOT A SESSION. A session belongs to the harness: it starts when somebody opens
a terminal, it ends when they close it, and its id means nothing to anyone else. A track
is what the WORK is called, so a new agent joins whichever one is current without knowing
anything about how it got there, and the same track survives any number of sessions,
compactions and restarts.

PARKED, NEVER CLOSED. Switching away keeps everything exactly as it stood — its pins, its
open work, its notes — and switching back finds it unchanged. There is no delete: the tool
this replaces dropped things quietly to stay tidy, and the whole point here is that nothing
disappears without somebody deciding it should.

THE SWAP IS THE IMPLEMENTATION, and it is deliberate. `pins` and `work` keep meaning "the
current thread's pins and work", so every other module keeps reading exactly what it read
before and none of them learn a new concept. Switching parks the live pair under the old
name and lifts the new pair into its place. A design where `pins.py` had to know about
tracks would have put the same idea in five files.
"""
from __future__ import annotations

from pathlib import Path

import state

#: The track every project already has before anyone names one. An existing journal
#: becomes this on the first switch, with nothing to migrate — `current` simply defaults.
DEFAULT = "default"

CURRENT, PARKED, PREVIOUS = "current", "tracks", "previous"


def current(root: Path) -> str:
    return state.get(root, CURRENT, DEFAULT) or DEFAULT


def _parked(root: Path) -> dict:
    got = state.get(root, PARKED, {})
    return got if isinstance(got, dict) else {}


def listing(root: Path) -> list[dict]:
    """Every track, current one first, with enough to tell them apart."""
    here = current(root)
    out = [{
        "name": here,
        "current": True,
        "pins": len([p for p in state.get(root, "pins", []) if not p.get("struck")]),
        "open": len([w for w in state.get(root, "work", []) if not w.get("ended")]),
        "at": "",
    }]
    for name, held in sorted(_parked(root).items()):
        out.append({
            "name": name,
            "current": False,
            "pins": len([p for p in held.get("pins", []) if not p.get("struck")]),
            "open": len([w for w in held.get("work", []) if not w.get("ended")]),
            "at": held.get("at", ""),
        })
    return out


def switch(root: Path, name: str, at: str) -> tuple[bool, str]:
    """Park what is live and lift another track into its place. Creates one if new.

    IT REFUSES AN EMPTY NAME AND NEVER GUESSES ONE. A track with no name is one nobody
    can switch back to, which makes parking it the same as losing it.
    """
    name = " ".join((name or "").split())
    if not name:
        return False, 'switch to what? `journal switch "<thread>"`, or `--back`'
    # ONE TRANSACTION. The swap is five writes, and a `remember` from another session
    # landing between the second and the third would file its pin on the wrong track's
    # slot and then be overwritten. The record is shared; the swap holds it throughout.
    with state.locked(root):
        return _switch(root, name, at)


def _switch(root: Path, name: str, at: str) -> tuple[bool, str]:
    here = current(root)
    if name == here:
        return False, f"already on {name}"

    parked = _parked(root)
    parked[here] = {
        "pins": state.get(root, "pins", []),
        "work": state.get(root, "work", []),
        "at": at,
    }
    taking = parked.pop(name, None)
    fresh = taking is None

    state.put(root, PARKED, parked)
    state.put(root, "pins", (taking or {}).get("pins", []))
    state.put(root, "work", (taking or {}).get("work", []))
    state.put(root, PREVIOUS, here)
    state.put(root, CURRENT, name)

    kept = f"{name} is new" if fresh else (
        f"{len([p for p in (taking or {}).get('pins', []) if not p.get('struck')])} pin(s), "
        f"{len([w for w in (taking or {}).get('work', []) if not w.get('ended')])} open"
    )
    return True, f"on {name} — {kept}\n  {here} is parked, exactly as you left it"


def back(root: Path, at: str) -> tuple[bool, str]:
    with state.locked(root):
        was = state.get(root, PREVIOUS)
        if not was:
            return False, "no track to go back to — nothing has been switched away from yet"
        return switch(root, was, at)
