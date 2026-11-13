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

# Every event kind, stated once in the binary's table (cli/hooks.EventKinds) and written beside this script.
EVENT_KINDS = json.loads((Path(__file__).resolve().parent / "events.json").read_text())


def names_where(fact: str) -> set:
    return {name for kind in EVENT_KINDS if kind.get(fact) for name in kind["names"]}


JUDGED_LATER = names_where("judged_later")
HANDED_IN = names_where("handed_in")
MERGED = names_where("merged")
EDITED = next(kind["key"] for kind in EVENT_KINDS if kind.get("edits"))
WORKER_ROLES = {"worker", "helper", "medior", "senior", "builder"}

LIVE_EDITS, WORKERS, WORKER_EDITS = "judge_edits", "judge_workers", "judge_worker_edits"
HAND_INS, HAND_INS_AFTER = "judge_hand_ins", "judge_hand_ins_after"
# The guards that hold a step on a judging, each with the setting that asks for it.
GUARDED = {name: kind["guarded_by"] for kind in EVENT_KINDS if kind.get("guarded_by") for name in kind["names"]}
EVENTS, WINDOWS, TOLD, QUEUE = "events", "windows", "told", "queue"
JUDGE_EVENT, JUDGE_GUARD, JUDGE_AGENT = "--judge-event=", "--judge-guard=", "--judge-agent="
EDIT_WINDOW, DEFAULT_EDIT_WINDOW = "judge_edit_window", 5.0
FAULTS = "hook-faults.log"
TAKING_AT_MOST = 60
NEW_CARD_EACH_TIME = {"sin-found", "sin-resolved"}
UNNAMED_WINDOW_STANDS = 30
HELD_AT_MOST = 2 * 60 * 60
EDIT_WINDOW_AT_MOST = 60.0
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


def told_after(fields: dict) -> dict:
    """An answer given after its event can no longer refuse it, so a refusal is told, with its note beside it."""
    refuse = fields.get("refuse")
    if refuse is None:
        return fields
    whisper = fields.get("whisper")
    told = refuse if whisper is None or whisper == refuse else f"{refuse}\n\n{whisper}"
    return {**{key: value for key, value in fields.items() if key != "refuse"}, "whisper": told}


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
        answer = shaped(told_after(fields))
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
    return role_of(event) not in WORKER_ROLES or workers_chose(chosen, WORKER_EDITS)


def role_of(event: dict) -> str:
    """The agent's role as the binary reads it: the event's own, else its data's, trimmed and in lower case."""
    data = event.get("data") if isinstance(event.get("data"), dict) else {}
    return str(event.get("role") or data.get("role") or "").strip().lower()


def workers_chose(chosen: dict, key: str) -> bool:
    return switched_on(chosen.get(WORKERS)) and switched_on(chosen.get(key))


def judged_after_the_hand_in(event: dict) -> bool:
    """A worker's hand-in that goes through at once and is judged after, the worker told what was found: judging
    after it chosen, and judging before it not."""
    chosen = settings_of(event)
    return event.get("event") in HANDED_IN and workers_chose(chosen, HAND_INS_AFTER) and not workers_chose(chosen, HAND_INS)


def judged_after_the_merge(event: dict) -> bool:
    """A worker's merge that landed, judged after with merge judging chosen: what it brought in is told later."""
    return event.get("event") in MERGED and workers_chose(settings_of(event), "judge_merges")


HELPERS_CLI, HELPERS_CLI_TITLE = "workers_cli", "Helpers may run the commandments CLI"
CLI_PROGRAMS = {"commandments", "commandments-go", "commandments-release"}
MANIFEST = PLUGIN / "journal-plugin.5.json"
COMMAND_SEPARATORS = ("&&", "||", ";", "|", "(", ")", "{", "}", "`", '"', "'")
COMMAND_WRAPPERS = {"env", "nice", "nohup", "exec", "command", "time", "timeout", "xargs", "sudo", "caffeinate"}
COMMAND_STARTING = "command_starting"
# Events the binary has nothing to add to once the script has refused what it refuses: answered at once, judged later.
ANSWERED_HERE = names_where("answered_here")


def is_assignment(word: str) -> bool:
    name, assigns, _ = word.partition("=")
    return bool(assigns) and name != "" and not any(mark in name for mark in "/-.$")


def is_number(word: str) -> bool:
    return word.strip("0123456789.smhd") == ""


def names_the_cli(program: str) -> bool:
    return Path(program).name in CLI_PROGRAMS


def program_of(words: list) -> str:
    while len(words) > 1 and Path(words[0]).name in COMMAND_WRAPPERS:
        words = words[1:]
        while len(words) > 1 and (words[0].startswith("-") or is_assignment(words[0]) or is_number(words[0])):
            words = words[1:]
    return words[0]


def cli_aliases(manifest: Path = MANIFEST) -> set[str]:
    """The journal lines that run the CLI by an alias this plugin declares, read from its manifest."""
    try:
        declared = json.loads(manifest.read_text()).get("aliases") or []
    except (OSError, ValueError):
        return set()
    return {f"journal {alias['name']}" for alias in declared if isinstance(alias, dict) and alias.get("name")}


def runs_the_cli(command: str) -> bool:
    """Whether a shell line runs the commandments CLI anywhere in it: by a journal alias, by its program, through a
    wrapper such as env or nice, or by a path to it."""
    for separator in COMMAND_SEPARATORS:
        command = command.replace(separator, "\n")
    aliases = cli_aliases()
    for part in command.split("\n"):
        words = part.split()
        if " ".join(words[:2]) in aliases:
            return True
        while words and is_assignment(words[0]):
            if names_the_cli(words[0].split("=", 1)[1]):
                return True
            words = words[1:]
        if words and (names_the_cli(program_of(words)) or any("/" in word and names_the_cli(word) for word in words)):
            return True
    return False


def cli_refused(event: dict) -> str:
    """The refusal of a helper's command that runs the commandments CLI while the setting keeps helpers from it."""
    data = event.get("data") if isinstance(event.get("data"), dict) else {}
    command, role = data.get("text") or "", role_of(event)
    if event.get("event") != COMMAND_STARTING or role not in WORKER_ROLES or switched_on(settings_of(event).get(HELPERS_CLI)):
        return ""
    if not runs_the_cli(str(command)):
        return ""
    return (f"Code Commandments — a {role} may not run the commandments CLI or the judge here: the setting "
            f"\"{HELPERS_CLI_TITLE}\" ({HELPERS_CLI}) is off. Your work is judged when it is handed in and when it is merged.")


class EditWindow:
    """One agent's events waiting to be judged, of every kind: the first opens the window and queues the one run that
    judges them, every later one only joins it, and that run takes them all when it starts. So an agent has at most one
    judging waiting in the queue, however long it waits. The window names the process that holds it: the journal's
    `plugin run`, which waits on its runner through the queue and the run and ends only when the command ends, is
    stopped from the queue or reaches its limit. The window stands while that process lives and for two hours at most,
    the journal's queue wait plus its run limit, so a run that crashes, is cancelled or is killed frees it at once and a
    reused pid never holds it for long. A window not yet named stands for a few seconds only. Taken events stay on disk
    until they are judged: a run that dies before it finishes leaves them for the next run."""

    def __init__(self, data: Path, agent: int) -> None:
        self.folder, self.agent = data / WINDOWS, agent

    def path(self, suffix: str) -> Path:
        return self.folder / f"{self.agent}{suffix}"

    def gather(self, line: bytes) -> bool:
        self.folder.mkdir(parents=True, exist_ok=True)
        swept(self.folder)
        with open(self.path(".lock"), "a") as lock:
            fcntl.flock(lock, fcntl.LOCK_EX)
            self.restore_abandoned()
            with open(self.path(".events"), "ab") as events:
                events.write(line.strip() + b"\n")
            if self.waiting():
                return False
            self.path(".open").write_text("")
            return True

    def waiting(self) -> bool:
        opened = self.path(".open")
        try:
            holder, age = opened.read_text().strip(), time.time() - opened.stat().st_mtime
        except OSError:
            return False
        if holder.isdigit():
            return age < HELD_AT_MOST and alive(int(holder))
        return age < UNNAMED_WINDOW_STANDS

    def names(self, holder: int) -> None:
        with open(self.path(".lock"), "a") as lock:
            fcntl.flock(lock, fcntl.LOCK_EX)
            opened = self.path(".open")
            if opened.exists():
                opened.write_text(str(holder))

    def reopen(self) -> None:
        with open(self.path(".lock"), "a") as lock:
            fcntl.flock(lock, fcntl.LOCK_EX)
            self.path(".open").unlink(missing_ok=True)

    def pending(self) -> list[bytes]:
        return lines_of(self.path(".events"))

    def take(self) -> Path | None:
        """The events gathered so far, moved aside for this run, and the window closed, so the next event queues a run
        of its own; the run removes the file once it has judged them."""
        self.folder.mkdir(parents=True, exist_ok=True)
        with open(self.path(".lock"), "a") as lock:
            fcntl.flock(lock, fcntl.LOCK_EX)
            self.restore_abandoned()
            taking = self.path(f".taking.{os.getpid()}")
            self.path(".open").unlink(missing_ok=True)
            try:
                self.path(".events").rename(taking)
            except OSError:
                return None
            return taking

    def restore_abandoned(self) -> None:
        """Events a run took and died before judging go back to wait for the next run."""
        for taking in self.folder.glob(f"{self.agent}.taking.*"):
            run = taking.name.rsplit(".", 1)[-1]
            if run.isdigit() and alive(int(run)):
                continue
            with open(self.path(".events"), "ab") as events:
                events.write(taking.read_bytes())
            taking.unlink(missing_ok=True)


def lines_of(path: Path) -> list[bytes]:
    try:
        return [line for line in path.read_bytes().splitlines() if line.strip()]
    except OSError:
        return []


def swept(folder: Path) -> None:
    """Drops what an agent left in its window a day ago and nothing touched since."""
    for left in folder.iterdir():
        try:
            if time.time() - left.stat().st_mtime > EVENT_KEPT_AT_MOST:
                left.unlink(missing_ok=True)
        except OSError:
            continue


def guard_queued(event: dict, line: bytes) -> dict | None:
    """A guard's answer when its judging is chosen: the journal queues the run that judges the step and holds the step
    on it, with no time limit, so the judging is never cut short. It is the one judging that does not join the agent's
    window: the step waits on this run's own verdict."""
    key, data = GUARDED.get(event.get("event")), os.environ.get("JOURNAL_PLUGIN_DATA", "")
    if not key or not data or not workers_chose(settings_of(event), key):
        return None
    kept = kept_event(Path(data), line)
    return {"v": 1, "answer": "queue", "run": {"kind": "judge", "command": [str(PLUGIN / ".journal-plugin" / "hook"), f"{JUDGE_GUARD}{kept}"]}}


def guard_judged(event: str, binary: Path = BINARY) -> int:
    """The queued run a guard holds its step on: it passes with nothing found, and refuses with the reason printed."""
    kept = Path(event)
    try:
        line = kept.read_bytes()
    except OSError:
        return 0
    kept.unlink(missing_ok=True)
    try:
        said = json.loads(answered(line, binary)[0])
    except OSError:
        print("Code Commandments could not start its checker, so this step was not judged.")
        return 0
    if said.get("answer") == "refuse":
        print(said.get("reason", ""))
        return 1
    if said.get("tell"):
        print(said["tell"])
    return 0


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


def judging_line(journal: str, agent: int, program: Path = PLUGIN / ".journal-plugin" / "hook") -> list[str]:
    agent_named = ["--agent", str(agent)] if agent else []
    return [journal, "plugin", "run", "--kind", "judge", *agent_named, "--", str(program), f"{JUDGE_AGENT}{agent}"]


def queued(agent: int) -> int | None:
    """Starts the journal's run that judges an agent's waiting events, detached, so the event's answer never waits;
    its pid holds the agent's window."""
    journal = journal_program()
    if not journal:
        return None
    try:
        run = subprocess.Popen(judging_line(journal, agent), stdin=subprocess.DEVNULL, stdout=subprocess.DEVNULL,
                               stderr=subprocess.DEVNULL, start_new_session=True)
    except OSError:
        return None
    return run.pid


def alive(pid: int) -> bool:
    """Whether a process of ours still runs under pid: one of another user is never the journal's run."""
    try:
        os.kill(pid, 0)
    except (ProcessLookupError, PermissionError):
        return False
    return True


def agent_of(event: dict) -> int:
    return event["agent"] if isinstance(event.get("agent"), int) else 0


def judged_for_the_agent(line: bytes, agent: int) -> None:
    """Every judging the plugin queues goes through here: the event joins the agent's waiting run, or opens the
    agent's window and queues that one run."""
    data = os.environ.get("JOURNAL_PLUGIN_DATA", "")
    if not data:
        return
    window = EditWindow(Path(data), agent)
    if not window.gather(line):
        return
    try:
        run = queued(agent)
        if run is not None:
            window.names(run)
    except Exception:
        window.reopen()
        raise
    if run is None:
        window.reopen()


def judge_later(line: bytes) -> None:
    try:
        event = json.loads(line)
    except ValueError:
        return
    if isinstance(event, dict) and judged_later(event):
        judged_for_the_agent(line, agent_of(event))


def edit_window_of(lines: list[bytes]) -> float:
    """How long an agent's edits are gathered before they are judged together: the setting the last edit carried."""
    for line in reversed(lines):
        try:
            event = json.loads(line)
        except ValueError:
            continue
        if isinstance(event, dict) and event.get("event") == EDITED:
            try:
                return min(EDIT_WINDOW_AT_MOST, max(0.0, float(settings_of(event).get(EDIT_WINDOW, DEFAULT_EDIT_WINDOW))))
            except (TypeError, ValueError, OverflowError):
                return DEFAULT_EDIT_WINDOW
    return 0.0


def judged_agent(agent_named: str, binary: Path = BINARY) -> int:
    """The one queued run of an agent: it waits out the edit window, takes every event the agent gathered, judges the
    edits as one batch and every other event on its own, and only then lets them go."""
    data = os.environ.get("JOURNAL_PLUGIN_DATA", "")
    if not data:
        return 0
    window = EditWindow(Path(data), int(agent_named) if agent_named.isdigit() else 0)
    time.sleep(edit_window_of(window.pending()))
    taking = window.take()
    if taking is None:
        return 0
    code = 0
    for batch in batches_of(lines_of(taking)):
        code = judged(str(kept_event(Path(data), b"\n".join(batch))), binary) or code
    taking.unlink(missing_ok=True)
    return code


def batches_of(lines: list[bytes]) -> list[list[bytes]]:
    """What one run judges: every edit together, each hand-in or merge on its own, and of the moments judged later
    (a command, a tick, a stop) only the latest of each, which sees everything the earlier ones would have."""
    edits = [line for line in lines if edited(line)]
    latest: dict = {}
    for line in lines:
        name = event_name_of(line)
        if name in JUDGED_LATER and name != EDITED:
            latest[name] = line
    others = [line for line in lines if not edited(line) and (event_name_of(line) not in latest or latest[event_name_of(line)] is line)]
    return ([edits] if edits else []) + [[line] for line in others]


def event_name_of(line: bytes) -> str:
    try:
        event = json.loads(line)
    except ValueError:
        return ""
    return str(event.get("event", "")) if isinstance(event, dict) else ""


def edited(line: bytes) -> bool:
    return event_name_of(line) == EDITED


def main() -> int:
    judging = [argument[len(JUDGE_EVENT):] for argument in sys.argv[1:] if argument.startswith(JUDGE_EVENT)]
    if judging:
        return judged(judging[0])
    guarding = [argument[len(JUDGE_GUARD):] for argument in sys.argv[1:] if argument.startswith(JUDGE_GUARD)]
    if guarding:
        return guard_judged(guarding[0])
    agents = [argument[len(JUDGE_AGENT):] for argument in sys.argv[1:] if argument.startswith(JUDGE_AGENT)]
    if agents:
        codes: list = []
        afterwards(lambda: codes.append(judged_agent(agents[0])))
        return codes[0] if codes else 1
    line = sys.stdin.buffer.read()
    try:
        event = json.loads(line)
    except ValueError:
        event = None
    refusal = cli_refused(event) if isinstance(event, dict) else ""
    if refusal:
        sys.stdout.write(json.dumps(shaped({"refuse": refusal}), ensure_ascii=False) + "\n")
        afterwards(lambda: judge_later(line))
        return 0
    data = os.environ.get("JOURNAL_PLUGIN_DATA", "")
    if data and isinstance(event, dict) and event.get("event") in ANSWERED_HERE:
        sys.stdout.write(json.dumps(shaped({})) + "\n")
        afterwards(lambda: judge_later(line))
        afterwards(lambda: delivered(Path(data)))
        return 0
    guard = guard_queued(event, line) if isinstance(event, dict) else None
    if guard:
        sys.stdout.write(json.dumps(guard) + "\n")
        return 0
    if isinstance(event, dict) and (judged_after_the_hand_in(event) or judged_after_the_merge(event)):
        sys.stdout.write(json.dumps(shaped({})) + "\n")
        afterwards(lambda: judged_for_the_agent(line, agent_of(event)))
        return 0
    if isinstance(event, dict) and (event.get("event") in GUARDED or event.get("event") in MERGED):
        sys.stdout.write(json.dumps(shaped({})) + "\n")
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
