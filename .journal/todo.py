"""Delayed work — what the agent should remember TO DO. Not a rule, not a pin, not in flight.

A pin is a claim, a rule binds, open work is in flight. None of them holds "do this later",
and a piece of work that is only remembered in a summary is a piece of work that is
forgotten at the next compaction. So a to-do is written down, and it is written as a FILE:
a to-do is a brief, not a claim, and when it is picked up in a week the reader needs what,
why and where to start, which is longer than one line. A file can be edited by hand and
read in a diff.

SCOPED TO THE TRACK. A to-do belongs to the line of work that deferred it, and one track's
debts do not bleed into another's: `todo/<track>/NNN-<slug>.md`. The number is the file's,
stable for the life of the to-do, so "to-do 3" means the same thing after 2 is done.

SAID, NEVER HELD, AND NOT AT EVERY STOP. An idle agent told "three to-dos are waiting"
will start one; whether it should is the user's call. The line says so, and it is said once
per transcript and again only when the list has changed — a reminder at every idle stop is
wallpaper within the hour.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

DIR = "todo"
FIELDS = ("title", "track", "at", "session", "line", "started", "done", "how")


def _slug(text: str, limit: int = 40) -> str:
    s = re.sub(r"[^a-z0-9]+", "-", text.lower()).strip("-")
    return (s[:limit].rstrip("-") or "untitled")


def folder(root: Path, track: str) -> Path:
    return root / DIR / _slug(track, 60)


def _parse(path: Path) -> dict:
    text = path.read_text()
    meta: dict = {"title": "", "body": "", "path": path}
    if text.startswith("---\n"):
        end = text.find("\n---", 4)
        if end != -1:
            for line in text[4:end].splitlines():
                if ":" in line:
                    k, v = line.split(":", 1)
                    meta[k.strip()] = v.strip()
            text = text[end + 4:].lstrip("\n")
    meta["body"] = text.strip()
    m = re.match(r"(\d+)-", path.name)
    meta["n"] = int(m.group(1)) if m else 0
    if not meta["title"]:
        meta["title"] = path.stem
    return meta


def _write(path: Path, meta: dict, body: str) -> None:
    lines = ["---"] + [f"{k}: {meta.get(k, '') or ''}" for k in FIELDS] + ["---", ""]
    if body.strip():
        lines += [body.strip(), ""]
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text("\n".join(lines))


def _all(root: Path, track: str) -> list[dict]:
    d = folder(root, track)
    if not d.is_dir():
        return []
    return sorted((_parse(f) for f in d.glob("*.md")), key=lambda m: m["n"])


def open_items(root: Path, track: str) -> list[dict]:
    return [t for t in _all(root, track) if not t.get("done")]


def _get(root: Path, track: str, n: int) -> tuple[dict | None, str]:
    items = {t["n"]: t for t in _all(root, track)}
    if n not in items:
        return None, f"there is no to-do {n} on track `{track}`. `journal todo` numbers them."
    return items[n], ""


def add(root: Path, track: str, title: str, body: str, at: str, where: dict | None = None) -> tuple[bool, str]:
    """Write one. Refuses an empty title and a duplicate open one."""
    title = " ".join((title or "").split())
    if not title:
        return False, 'a to-do needs a title: journal todo "<what, in a few words>"'
    for t in open_items(root, track):
        if t["title"].lower() == title.lower():
            return False, f"already waiting as to-do {t['n']} — nothing to add"
    items = _all(root, track)
    n = (items[-1]["n"] if items else 0) + 1
    path = folder(root, track) / f"{n:03d}-{_slug(title)}.md"
    meta = {"title": title, "track": track, "at": at, **{k: str(v) for k, v in (where or {}).items()}}
    _write(path, meta, body)
    return True, f"to-do {n} on `{track}`: {title}\n  {path.relative_to(root.parent)}"


def _update(root: Path, track: str, n: int, **fields) -> tuple[dict | None, str]:
    t, err = _get(root, track, n)
    if t is None:
        return None, err
    meta = {k: t.get(k, "") for k in FIELDS}
    meta.update({k: v for k, v in fields.items()})
    _write(t["path"], meta, t["body"])
    return {**t, **meta}, ""


def start(root: Path, track: str, n: int, at: str) -> tuple[dict | None, str]:
    t, err = _get(root, track, n)
    if t is None:
        return None, err
    if t.get("done"):
        return None, f"to-do {n} is already done ({t.get('how') or 'no reason recorded'})"
    return _update(root, track, n, started=at)


def done(root: Path, track: str, n: int, how: str, at: str) -> tuple[bool, str]:
    how = " ".join((how or "").split())
    if not how:
        return False, 'say how it was resolved: journal todo done <n> "<how>"'
    t, err = _get(root, track, n)
    if t is None:
        return False, err
    if t.get("done"):
        return False, f"to-do {n} is already done ({t.get('how')})"
    _update(root, track, n, done=at, how=how)
    return True, f"done {n}: {t['title']}\n  {how}"


def close_titled(root: Path, track: str, title: str, at: str) -> str | None:
    """When work with a to-do's title ends, the to-do is done too. The number, if so."""
    want = " ".join(title.split()).lower()
    for t in open_items(root, track):
        if t["title"].lower() == want and t.get("started"):
            _update(root, track, t["n"], done=at, how="closed with the work of the same name")
            return str(t["n"])
    return None


def render(root: Path, track: str, *, all_of_them: bool = False) -> str:
    items = _all(root, track) if all_of_them else open_items(root, track)
    if not items:
        return "Nothing is waiting." if not all_of_them else "No to-dos on this track."
    out = []
    for t in items:
        mark = "  done" if t.get("done") else ("  started" if t.get("started") else "")
        out.append(f"  {t['n']:>3}  {t['title']}{mark}")
    return "\n".join(out)


def show(root: Path, track: str, n: int) -> tuple[bool, str]:
    t, err = _get(root, track, n)
    if t is None:
        return False, err
    head = [f"# to-do {n} on `{track}`: {t['title']}"]
    meta = []
    if t.get("at"):
        meta.append(f"written {t['at'][:16]}")
    if t.get("line"):
        meta.append(f"line {t['line']}")
    if t.get("started"):
        meta.append(f"started {t['started'][:16]}")
    if t.get("done"):
        meta.append(f"done {t['done'][:16]}: {t.get('how')}")
    head.append("  " + " · ".join(meta) if meta else "")
    head.append(f"  {t['path'].relative_to(root.parent)}")
    body = t["body"] or "  (no brief beyond the title)"
    return True, "\n".join(head) + "\n\n" + body


def carry(root: Path, track: str) -> str:
    """The line a session start hands over. Titles only, and not an instruction."""
    waiting = open_items(root, track)
    if not waiting:
        return ""
    return (
        f"TO DO on this track, {len(waiting)} waiting — delayed work, not an instruction to "
        "start any of it:\n"
        + "\n".join(f"  {t['n']:>3}  {t['title']}" for t in waiting)
        + "\n`journal todo <n>` reads the brief; `journal todo start <n>` picks one up."
    )
