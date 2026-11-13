"""The plugin's answer to every event the agent journal hands it: one JSON line in, one out. The pinned release binary
judges; this script decides which events are judged later, queues that judging in the journal, and shapes every answer
the binary gives into the journal's words, so a change to any of it ships without a build."""

from __future__ import annotations

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
HANDED_IN = {"worker_delivering", "suites.coding.workers.worker_delivering"}
EDITED = "file_edited"
WORKER_ROLES = {"worker", "helper", "medior", "senior", "builder"}

LIVE_EDITS, WORKERS, WORKER_EDITS = "judge_edits", "judge_workers", "judge_worker_edits"
HAND_INS, HAND_INS_AFTER = "judge_hand_ins", "judge_hand_ins_after"
EVENTS, WINDOWS, TOLD, QUEUE = "events", "windows", "told", "queue"
JUDGE_EVENT = "--judge-event="
FAULTS = "hook-faults.log"
TAKING_AT_MOST = 60
NEW_CARD_EACH_TIME = {"sin-found", "sin-resolved"}
WINDOW_LEFT_OPEN_AFTER = 15 * 60
QUEUED_AT_MOST = 60 * 60
EVENT_KEPT_AT_MOST = 24 * 60 * 60


def answered(line: bytes, binary: Path = BINARY) -> tuple[bytes, int]:
    run = subprocess.run([str(binary), "plugin-hook"], input=line, stdout=subprocess.PIPE)
    return (json.dumps(shaped(fields_of(run.stdout)), ensure_ascii=False) + "\n").encode(), run.returncode


def fields_of(output: bytes) -> dict:
    lines = [line for line in output.decode(errors="replace").splitlines() if line.strip()]
    try:
        fields = json.loads(lines[-1]) if lines else {}
    except ValueError:
        return {}
    return fields if isinstance(fields, dict) else {}


_cards_shaped = 0


def card(raised: dict) -> dict:
    """A card the journal shows: a found sin and a clean file are each a new card, keyed so none replaces an earlier
    one and the chat keeps them in the order they came; any other card is kept up to date under its own key."""
    global _cards_shaped
    key = str(raised.get("key", ""))
    if raised.get("event") in NEW_CARD_EACH_TIME:
        _cards_shaped += 1
        key += f"@{time.time_ns():x}{_cards_shaped:x}"
    shown = {"type": raised.get("event"), "kind": raised.get("event"), "key": key, "label": raised.get("brief", "")}
    if raised.get("detail"):
        shown["detail"] = raised["detail"]
    return shown


def shaped(fields: dict) -> dict:
    """The binary's answer in the journal's words: refuse with a reason, or tell, with cards, required skills and how
    long the judging took. An answer already in the journal's words comes from a binary older than this script, the
    one a failed fetch left behind, and passes as it is."""
    if "v" in fields:
        return fields
    answer: dict = {"v": 1}
    refuse, whisper = fields.get("refuse"), fields.get("whisper")
    if refuse is not None and whisper is not None and refuse != whisper:
        answer["answer"], answer["reason"] = "refuse", f"{refuse}\n\n{whisper}"
    elif refuse is not None:
        answer["answer"], answer["reason"] = "refuse", refuse
    elif whisper is not None:
        answer["answer"], answer["tell"] = "tell", whisper
    cards = [card(raised) for raised in fields.get("raises") or [] if isinstance(raised, dict) and raised.get("key")]
    if cards:
        answer["cards"] = cards
    if fields.get("requires"):
        answer["require_skills"] = fields["requires"]
    if len(answer) > 1 and fields.get("judged_ms"):
        answer["judged_ms"], answer["read_ms"] = fields["judged_ms"], fields.get("read_ms", 0)
        if fields.get("judged_total_ms"):
            answer["judged_total_ms"] = fields["judged_total_ms"]
    return answer


def delivered(data: Path) -> None:
    """Moves every answer a judging told after its event into the journal's queue, in the journal's words. A file is
    taken by renaming it first, so two scripts never deliver one answer twice."""
    told, queue = data / TOLD, data / QUEUE
    for stranded in told.glob("*.taking") if told.is_dir() else []:
        try:
            if time.time() - stranded.stat().st_mtime > TAKING_AT_MOST:
                stranded.rename(stranded.with_suffix(".json"))
        except OSError:
            continue
    for waiting in sorted(told.glob("*.json")) if told.is_dir() else []:
        taken = waiting.with_suffix(".taking")
        try:
            waiting.rename(taken)
        except OSError:
            continue
        try:
            fields = json.loads(taken.read_text())
        except ValueError:
            fields = None
        if not isinstance(fields, dict):
            taken.unlink(missing_ok=True)
            continue
        answer = shaped(fields)
        for routing in ("env", "agent"):
            if routing in fields:
                answer[routing] = fields[routing]
        queue.mkdir(parents=True, exist_ok=True)
        written = queue / (waiting.stem + ".tmp")
        written.write_text(json.dumps(answer, ensure_ascii=False) + "\n")
        written.rename(queue / waiting.name)
        taken.unlink(missing_ok=True)


def judged(event: str, binary: Path = BINARY) -> int:
    """The queued run that judges a kept event: the binary judges and tells, and its answers go to the journal."""
    try:
        code = subprocess.run([str(binary), "plugin-hook", f"{JUDGE_EVENT}{event}"]).returncode
    except OSError:
        Path(event).unlink(missing_ok=True)
        return 1
    data = os.environ.get("JOURNAL_PLUGIN_DATA", "")
    if data:
        delivered(Path(data))
    return code


def switched_on(value: object) -> bool:
    return value is True or str(value) == "true"


def settings_of(event: dict) -> dict:
    given = event.get("settings")
    if isinstance(given, dict) and given:
        return given
    named = os.environ.get("JOURNAL_SETTINGS", "").strip()
    try:
        text = named if named.startswith("{") else (Path(named).read_text() if named else "")
        chosen = json.loads(text) if text else {}
    except (OSError, ValueError):
        return {}
    return chosen if isinstance(chosen, dict) else {}


def judged_later(event: dict) -> bool:
    """An event whose judging the journal runs after the event: an edit, a command, a tick or a stop, with live
    judging on, and for a worker's own edits only with worker judging on too."""
    if event.get("event") not in JUDGED_LATER:
        return False
    chosen = settings_of(event)
    if not switched_on(chosen.get(LIVE_EDITS)):
        return False
    return event.get("role") not in WORKER_ROLES or workers_chose(chosen, WORKER_EDITS)


def workers_chose(chosen: dict, key: str) -> bool:
    return switched_on(chosen.get(WORKERS)) and switched_on(chosen.get(key))


def judged_after_the_hand_in(event: dict) -> bool:
    """A worker's hand-in that goes through at once and is judged after, the worker told what was found: judging
    after it chosen, and judging before it not."""
    chosen = settings_of(event)
    return event.get("event") in HANDED_IN and workers_chose(chosen, HAND_INS_AFTER) and not workers_chose(chosen, HAND_INS)


class EditWindow:
    """One agent's live edits gathered on disk while its window is open: the first edit opens it and queues the one
    run that judges them, every later edit only joins it, and that run (the binary's) closes it when it starts and
    takes them all. So an agent has at most one live-edit judging waiting in the queue, however long it waits: the
    window names that run's kept event, which the run removes as it starts. A window whose run never started counts as
    closed after an hour, and one whose run died after starting once it has stood open far longer than any window."""

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
            if self.waiting():
                return False
            self.path(".open").write_text("")
            return True

    def waiting(self) -> bool:
        opened = self.path(".open")
        if not opened.exists():
            return False
        try:
            named = Path(opened.read_text().strip() or "/nonexistent")
            if named.is_file():
                return time.time() - named.stat().st_mtime < QUEUED_AT_MOST
            return time.time() - opened.stat().st_mtime < WINDOW_LEFT_OPEN_AFTER
        except OSError:
            return False

    def names(self, event: Path) -> None:
        with open(self.path(".lock"), "a") as lock:
            fcntl.flock(lock, fcntl.LOCK_EX)
            opened = self.path(".open")
            if opened.exists():
                opened.write_text(str(event))

    def reopen(self) -> None:
        with open(self.path(".lock"), "a") as lock:
            fcntl.flock(lock, fcntl.LOCK_EX)
            self.path(".open").unlink(missing_ok=True)


def kept_event(data: Path, line: bytes) -> Path:
    folder = data / EVENTS
    folder.mkdir(parents=True, exist_ok=True)
    # shortcut: a kept event no run read is dropped after a day; upgrade to the project's cache_hours if that matters
    for old in folder.glob("event-*.json"):
        try:
            if time.time() - old.stat().st_mtime > EVENT_KEPT_AT_MOST:
                old.unlink(missing_ok=True)
        except OSError:
            continue
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


def judging_line(journal: str, event: Path, agent: int, program: Path = PLUGIN / ".journal-plugin" / "hook") -> list[str]:
    agent_named = ["--agent", str(agent)] if agent else []
    return [journal, "plugin", "run", "--kind", "judge", *agent_named, "--", str(program), f"{JUDGE_EVENT}{event}"]


def queued(line: bytes, agent: int) -> Path | None:
    """Keeps the event and starts the journal's run that judges it, detached, so the event's answer never waits."""
    data, journal = os.environ.get("JOURNAL_PLUGIN_DATA", ""), journal_program()
    if not data or not journal:
        return None
    event = kept_event(Path(data), line)
    try:
        subprocess.Popen(judging_line(journal, event, agent), stdin=subprocess.DEVNULL, stdout=subprocess.DEVNULL,
                         stderr=subprocess.DEVNULL, start_new_session=True)
    except OSError:
        event.unlink(missing_ok=True)
        return None
    return event


def judge_later(line: bytes) -> None:
    try:
        event = json.loads(line)
    except ValueError:
        return
    data = os.environ.get("JOURNAL_PLUGIN_DATA", "")
    if not data or not isinstance(event, dict) or not judged_later(event):
        return
    agent = event.get("agent") if isinstance(event.get("agent"), int) else 0
    window = EditWindow(Path(data), agent)
    event_name = event.get("event")
    if event_name == EDITED and not window.gather(line):
        return
    try:
        event = queued(line, agent)
        if event is not None and event_name == EDITED:
            window.names(event)
    except Exception:
        window.reopen()
        raise
    if event is None:
        window.reopen()


def main() -> int:
    judging = [argument[len(JUDGE_EVENT):] for argument in sys.argv[1:] if argument.startswith(JUDGE_EVENT)]
    if judging:
        return judged(judging[0])
    line = sys.stdin.buffer.read()
    try:
        event = json.loads(line)
    except ValueError:
        event = None
    if isinstance(event, dict) and judged_after_the_hand_in(event):
        sys.stdout.write(json.dumps(shaped({})) + "\n")
        queued(line, event.get("agent") if isinstance(event.get("agent"), int) else 0)
        return 0
    answer, code = answered(line)
    sys.stdout.buffer.write(answer)
    sys.stdout.buffer.flush()
    afterwards(lambda: judge_later(line))
    data = os.environ.get("JOURNAL_PLUGIN_DATA", "")
    if data:
        afterwards(lambda: delivered(Path(data)))
    return code


def afterwards(work) -> None:
    """Work done after the event is answered: a fault there is written to the plugin's fault log, never raised, so
    no event fails for what comes after its answer."""
    try:
        work()
    except Exception as fault:
        data = os.environ.get("JOURNAL_PLUGIN_DATA", "")
        if data:
            try:
                with open(Path(data) / FAULTS, "a") as log:
                    log.write(f"{time.strftime('%Y-%m-%d %H:%M:%S')} {type(fault).__name__}: {fault}\n")
            except OSError:
                pass


if __name__ == "__main__":
    sys.exit(main())
