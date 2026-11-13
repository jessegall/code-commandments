"""The plugin's answer to every event the agent journal hands it: one JSON line in, one out. The pinned release binary
answers the event; this script decides which events are judged later and queues that judging in the journal, so a
change to it ships without a build."""

import fcntl
import json
import os
import shutil
import subprocess
import sys
import tempfile
import time
from pathlib import Path

PLUGIN = Path(__file__).resolve().parent.parent
BINARY = PLUGIN / "bin" / "commandments-release"

JUDGED_LATER = {"command_starting", "file_edited", "ticked", "idle"}
EDITED = "file_edited"
WORKER_ROLES = {"worker", "helper", "medior", "senior", "builder"}

LIVE_EDITS, WORKERS, WORKER_EDITS = "judge_edits", "judge_worker_edits", "judge_workers"
EVENTS, WINDOWS = "events", "windows"
WINDOW_LEFT_OPEN_AFTER = 15 * 60
EVENT_KEPT_AT_MOST = 24 * 60 * 60


def answered(line: bytes, binary: Path = BINARY) -> tuple[bytes, int]:
    run = subprocess.run([str(binary), "plugin-hook"], input=line, stdout=subprocess.PIPE)
    return run.stdout, run.returncode


def switched_on(value: object) -> bool:
    return value is True or str(value) == "true"


def settings_of(event: dict) -> dict:
    given = event.get("settings")
    if isinstance(given, dict) and given:
        return given
    named = os.environ.get("JOURNAL_SETTINGS", "")
    for text in (named, Path(named).read_text() if named and Path(named).is_file() else ""):
        try:
            chosen = json.loads(text)
        except ValueError:
            continue
        if isinstance(chosen, dict):
            return chosen
    return {}


def judged_later(event: dict) -> bool:
    """An event whose judging the journal runs after the event: an edit, a command, a tick or a stop, with live
    judging on, and for a worker's own edits only with worker judging on too."""
    if event.get("event") not in JUDGED_LATER:
        return False
    chosen = settings_of(event)
    if not switched_on(chosen.get(LIVE_EDITS)):
        return False
    return event.get("role") not in WORKER_ROLES or switched_on(chosen.get(WORKER_EDITS)) and switched_on(chosen.get(WORKERS))


class EditWindow:
    """One agent's live edits gathered on disk while its window is open: the first edit opens it and queues the one
    run that judges them, every later edit only joins it, and that run (the binary's) closes it when it takes them. A
    window whose run died counts as closed once it has stood open far longer than any window lasts."""

    def __init__(self, data: Path, agent: int) -> None:
        self.folder, self.agent = data / WINDOWS, agent

    def path(self, suffix: str) -> Path:
        return self.folder / f"{self.agent}{suffix}"

    def gather(self, edit: bytes) -> bool:
        self.folder.mkdir(parents=True, exist_ok=True)
        with open(self.path(".lock"), "a") as lock:
            fcntl.flock(lock, fcntl.LOCK_EX)
            with open(self.path(".edits"), "ab") as edits:
                edits.write(edit.strip() + b"\n")
            opened = self.path(".open")
            if opened.exists() and time.time() - opened.stat().st_mtime < WINDOW_LEFT_OPEN_AFTER:
                return False
            opened.touch()
            return True

    def reopen(self) -> None:
        with open(self.path(".lock"), "a") as lock:
            fcntl.flock(lock, fcntl.LOCK_EX)
            self.path(".open").unlink(missing_ok=True)


def kept_event(data: Path, line: bytes) -> Path:
    folder = data / EVENTS
    folder.mkdir(parents=True, exist_ok=True)
    # shortcut: a kept event no run read is dropped after a day; upgrade to the project's cache_hours if that matters
    for old in folder.glob("event-*.json"):
        if time.time() - old.stat().st_mtime > EVENT_KEPT_AT_MOST:
            old.unlink(missing_ok=True)
    handle, name = tempfile.mkstemp(prefix="event-", suffix=".json", dir=folder)
    with os.fdopen(handle, "wb") as kept:
        kept.write(line)
    return Path(name)


def journal_program() -> str:
    named = os.environ.get("JOURNAL_BIN") or shutil.which("journal")
    if named:
        return named
    beside = Path(os.environ.get("JOURNAL_ROOT", "")) / ".journal" / "bin" / "journal"
    return str(beside) if beside.is_file() else ""


def judging_line(journal: str, event: Path, agent: int, binary: Path = BINARY) -> list[str]:
    agent_named = ["--agent", str(agent)] if agent else []
    return [journal, "plugin", "run", "--kind", "judge", *agent_named, "--", str(binary), "plugin-hook", f"--judge-event={event}"]


def queued(line: bytes, agent: int, binary: Path = BINARY) -> bool:
    """Keeps the event and starts the journal's run that judges it, detached, so the event's answer never waits."""
    data, journal = os.environ.get("JOURNAL_PLUGIN_DATA", ""), journal_program()
    if not data or not journal:
        return False
    event = kept_event(Path(data), line)
    try:
        subprocess.Popen(judging_line(journal, event, agent, binary), stdin=subprocess.DEVNULL, stdout=subprocess.DEVNULL,
                         stderr=subprocess.DEVNULL, start_new_session=True)
    except OSError:
        event.unlink(missing_ok=True)
        return False
    return True


def judge_later(line: bytes, binary: Path = BINARY) -> None:
    try:
        event = json.loads(line)
    except ValueError:
        return
    data = os.environ.get("JOURNAL_PLUGIN_DATA", "")
    if not data or not isinstance(event, dict) or not judged_later(event):
        return
    agent = event.get("agent") if isinstance(event.get("agent"), int) else 0
    window = EditWindow(Path(data), agent)
    if event.get("event") == EDITED and not window.gather(line):
        return
    if not queued(line, agent, binary):
        window.reopen()


def main() -> int:
    line = sys.stdin.buffer.read()
    answer, code = answered(line)
    sys.stdout.buffer.write(answer)
    sys.stdout.buffer.flush()
    judge_later(line)
    return code


if __name__ == "__main__":
    sys.exit(main())
