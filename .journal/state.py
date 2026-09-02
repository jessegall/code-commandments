"""`.journal/record.json` and `.journal/runtime/<transcript>.json` — two kinds of fact.

THE RECORD IS SHARED. Pins, work, tracks: what somebody decided. It belongs to the project,
survives a fresh clone, is the half worth reviewing in a diff, and every Claude Code session
and every agent inside one reads and writes the same file.

THE RUNTIME IS NOT. Where the untagged hold last fired, which context rung was announced,
the largest tool result so far, the floor under history the hook was not present for: each
of these is a LINE NUMBER OR A READING OF ONE TRANSCRIPT. Held at project scope they were
inherited by every later transcript — a session at line 53 carried `held_at: 1746` from the
one before it, so its untagged hold could not fire until line 1747, and a subagent's read
raised `biggest_result` in the parent's context. So the runtime is one small file per
transcript, keyed by the transcript's own name, and it is gitignored because it means
nothing in anybody else's checkout.

Which file a key lives in is DATA, not a rule to remember, because a rule about where to
write is one that is eventually written past.
"""
from __future__ import annotations

import contextlib
import json
import os
import sys
import tempfile
import time
from pathlib import Path

RECORD = "record.json"
RUNTIME_DIR = "runtime"
LOCK = "record.json.lock"
#: The project-wide runtime file this replaced. Retired on sight, never read: its marks are
#: line numbers of unknown provenance, and guessing which transcript they belonged to would
#: write them into the session running the upgrade — the defect being fixed.
RETIRED = "state.json"

IN_RECORD = {"pins", "work", "rules", "tracks", "current", "previous"}


def is_record(key: str) -> bool:
    return key in IN_RECORD


def record_file(root: Path) -> Path:
    return root / RECORD


def runtime_file(root: Path, stem: str) -> Path:
    return root / RUNTIME_DIR / f"{stem}.json"


def _read(f: Path) -> dict:
    if not f.is_file():
        return {}
    try:
        data = json.loads(f.read_text())
        return data if isinstance(data, dict) else {}
    except (ValueError, OSError):
        return {}  # a corrupt handle file must never stop the record being read


def _write(f: Path, data: dict) -> None:
    """Atomic, and SAFE UNDER CONCURRENT WRITERS.

    The first version wrote `<name>.tmp` and replaced it. Two hooks writing at once — and
    parallel tool calls fire PostToolUse at once — shared that path, and one of them died
    with FileNotFoundError when the other's replace consumed the tmp. Reproduced: four
    writers, three tracebacks. A crashing hook is rendered to the user as a hook error. So
    every writer gets its own tmp, and the loser of a race is overwritten, not killed.
    """
    f.parent.mkdir(parents=True, exist_ok=True)
    fd, tmp = tempfile.mkstemp(dir=f.parent, prefix=f".{f.name}.", suffix=".tmp")
    try:
        with os.fdopen(fd, "w") as fh:
            fh.write(json.dumps(data, indent=2) + "\n")
        os.replace(tmp, f)
    except BaseException:
        with contextlib.suppress(OSError):
            os.unlink(tmp)
        raise


def load(root: Path, name: str = RECORD) -> dict:
    """A whole file, by name relative to the root. `verify` reads runtime files this way."""
    return _read(root / name)


def runtime(root: Path, stem: str) -> dict:
    return _read(runtime_file(root, stem))


def runtime_files(root: Path) -> list[tuple[str, dict]]:
    """Every transcript's runtime marks, by stem. What `verify` counts as evidence."""
    d = root / RUNTIME_DIR
    if not d.is_dir():
        return []
    return sorted((f.stem, _read(f)) for f in d.glob("*.json"))


def get(root: Path, key: str, default=None, *, stem: str | None = None):
    if is_record(key):
        return _read(record_file(root)).get(key, default)
    if not stem:
        return default
    return runtime(root, stem).get(key, default)


def put(root: Path, key: str, value, *, stem: str | None = None) -> None:
    """Write one key. A runtime write with no transcript SAYS SO and does nothing.

    Not an exception: the hook's contract is that a crash is worse than silence, and a
    handler fed a payload without a transcript should be quiet about the mark rather than
    dead. Not a silent fallback to project scope either — that is the bug this module was
    rewritten to end.
    """
    if is_record(key):
        f = record_file(root)
    elif not stem:
        print(f"journal: no transcript to file {key!r} under — mark not written",
              file=sys.stderr)
        return
    else:
        f = runtime_file(root, stem)
    data = _read(f)
    data[key] = value
    _write(f, data)


def retire_old(root: Path) -> bool:
    """Set the project-wide runtime file aside, once. True if this call did it."""
    old = root / RETIRED
    if not old.is_file():
        return False
    try:
        old.rename(root / (RETIRED + ".retired"))
        return True
    except OSError:
        return False  # another process got there first; nothing to do


#: REENTRANT, by a depth counter. `tracks.switch` moves the record under the lock, and it
#: is written in terms of the same helpers a caller might already be holding the lock
#: through. A second `flock` on the same file in the same process blocks forever, verified;
#: a CLI that hangs until the tool timeout is worse than any lost pin.
_depth = 0
_held = None


@contextlib.contextmanager
def locked(root: Path, wait: float = 3.0):
    """Hold the record for one load-mutate-save.

    THE LOCK SPANS THE WHOLE OPERATION, not the write. A lock around `put` alone protects
    nothing: every caller loads, mutates, saves, and two of them interleaved both load eight
    pins and both write nine. The lock has to be taken before the load.

    BOUNDED. It waits a few seconds and then PROCEEDS with a line on stderr, because a hook
    has a timeout of its own and a wedged `journal remember` is a stalled tool with no
    message. A lost race under contention that long is a lost pin, which is visible in
    `pins`; a hang is not visible anywhere.
    """
    global _depth, _held
    if _depth:
        _depth += 1
        try:
            yield
        finally:
            _depth -= 1
        return
    try:
        import fcntl
    except ImportError:  # not POSIX: no lock, same behaviour as before this existed
        yield
        return
    root.mkdir(parents=True, exist_ok=True)
    fh = open(root / LOCK, "a+")
    deadline = time.monotonic() + wait
    got = False
    while True:
        try:
            fcntl.flock(fh, fcntl.LOCK_EX | fcntl.LOCK_NB)
            got = True
            break
        except OSError:
            if time.monotonic() >= deadline:
                print(f"journal: record locked for over {wait:.0f}s — proceeding without it",
                      file=sys.stderr)
                break
            time.sleep(0.02)
    _depth, _held = 1, fh
    try:
        yield
    finally:
        _depth, _held = 0, None
        if got:
            with contextlib.suppress(OSError):
                fcntl.flock(fh, fcntl.LOCK_UN)
        fh.close()
