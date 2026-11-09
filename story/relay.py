import hashlib
from dataclasses import asdict, dataclass, field
import json
import re
import os
import shutil
import signal
import socket
import socketserver
import sqlite3
import subprocess
import sys
import tempfile
import threading
import time

NOUNS = frozenset({
    "agent",
    "alias",
    "skill",
    "commandments",
    "commandments-judge-changes",
    "environment",
    "file-edit",
    "output",
    "plugin",
    "proof",
    "rules",
    "sins",
    "task",
    "worker",
    "worker-worktree",
})

STEP_SECONDS = 600

FILE_KINDS = frozenset({"write", "link", "remove"})
WORKTREES = os.path.join(".journal", "worktrees")
TEST_CHECKOUT = os.path.join(WORKTREES, "story-engine")
SCRATCH_PREFIX = "story_scratch"
WORKTREE_PREFIX = "story-engine-"
SLEEPER = ["sleep", "86400"]
BRANCH_PREFIX = "story-engine/story-"
STORY_ENVIRONMENT = "story-engine"
JOURNAL_STORE = os.path.join(".journal", "version-5.db")
MERGES_JUDGED = os.path.join(".journal", "plugin-data", "code-commandments", ".merged-judged")
NO_AGENT = "story-engine-no-agent"
LAUNCH_VARIABLES = ("HOME", "PATH", "USER", "LOGNAME", "SHELL", "LANG", "LC_ALL", "TMPDIR", "TERM")
WIRE_NAMES = {"in": "folder"}
PLUGINS = os.path.join(".journal", "plugins") + os.sep
BRIDGE_CACHE = os.path.join(".cache", "code-commandments") + os.sep
STOPPING_SIGNALS = {"SIGTERM": signal.SIGTERM, "SIGKILL": signal.SIGKILL}
BETWEEN_LOOKS_SECONDS = 0.5
PLUGIN_BINARY = os.path.join(".journal", "plugins", "code-commandments", "bin", "commandments-release")
SERVICE_VERBS = {"hooks": "journal-serve", "mypy": "mypy-serve", "frontend": "frontend-serve", "php": "php-serve", "roslyn": "roslyn-serve"}
UNDER_A_PARENT = '"$@" >../service.log 2>&1 & echo $!; wait'
LANGUAGE_SAMPLES = {
    "mypy": ("order.py", "def total() -> int:\n    return 1\n"),
    "frontend": ("order.ts", "export const total = 1;\n"),
    "php": ("order.php", "<?php\n\nfunction total(): int\n{\n    return 1;\n}\n"),
    "roslyn": ("Order.cs", "class Order { }\n"),
}
TORN_DOWN_WITHIN_SECONDS = 3
DETECTOR_MEMORY = os.path.join(".journal", "plugin-data", "code-commandments", "detector-memories.json")
AGENT_LINES = re.compile(r"agent-lines?[: ]\s*(\d+(?:(?:,\s*|\s+and\s+)\d+)*)")


class Refused(Exception):
    """A request the relay will not run, with the reason it is told."""


@dataclass(frozen=True)
class Asked:
    """One step a story asks for, as it came off the wire: what the Go side leaves out is absent here too."""

    kind: str
    argv: list[str] = field(default_factory=list)
    stdin: str = ""
    folder: str | None = None
    path: str | None = None
    many: int = 0
    pid: str | None = None
    name: str | None = None
    action: str | None = None
    actor: str | None = None
    role: str = "worker"
    session: str | None = None

    @classmethod
    def read(cls, line: bytes) -> "Asked":
        return cls(**{WIRE_NAMES.get(name, name): value for name, value in json.loads(line).items()})


@dataclass(frozen=True)
class Answer:
    """What a step answers a story: its exit code and what it printed."""

    code: int
    out: str = ""
    err: str = ""

    @classmethod
    def refused(cls, why: object) -> "Answer":
        return cls(126, "", f"the story relay refuses this: {why}\n")

    @classmethod
    def of(cls, done: subprocess.CompletedProcess) -> "Answer":
        return cls(done.returncode, done.stdout, done.stderr)


@dataclass(frozen=True)
class Process:
    """One process of this machine as ps lists it."""

    pid: int
    parent: int
    command: str

    @property
    def is_orphaned_bridge(self) -> bool:
        return self.parent == 1 and BRIDGE_CACHE in self.command and "--serve" in self.command

    def line(self) -> str:
        return f"{self.pid}\t{self.parent}\t{self.command}\n"


@dataclass(frozen=True)
class Throwaway:
    """A service a story started under a parent of its own, in a folder of its own, so the project's real one is never touched."""

    parent: subprocess.Popen
    service: int
    folder: str


class Step(socketserver.StreamRequestHandler):
    """One request: a journal command or a hook, run on this machine, answered with its output and exit code."""

    def handle(self) -> None:
        try:
            answer = self.server.run(Asked.read(self.rfile.readline()))
        except Refused as refusal:
            answer = Answer.refused(refusal)
        except (ValueError, KeyError, TypeError) as broken:
            answer = Answer(126, "", f"the story relay could not read the request: {broken}\n")
        self.wfile.write((json.dumps(asdict(answer)) + "\n").encode())


class Relay(socketserver.ThreadingTCPServer):
    """The host side of the story engine. The stories run inside the capped Linux container, and the journal is a
    program of this machine reached through its own Unix socket, so the relay runs what a story asks for: one JSON
    line per connection on 127.0.0.1, only a fixed list of journal nouns and the journal's hook entry, alive only
    while scripts/stories runs it. It acts as whoever started it, so a story never borrows another identity."""

    allow_reuse_address = True
    daemon_threads = True

    def __init__(self, project: str) -> None:
        super().__init__(("127.0.0.1", 0), Step)
        self.project = project
        self.home = ""
        self.sleepers: dict[int, subprocess.Popen] = {}
        self.throwaways: dict[int, Throwaway] = {}
        self.frozen: set[int] = set()
        self.holding = threading.Lock()
        self.journal = os.path.join(project, ".journal", "bin", "journal")
        self.hook = os.path.join(project, ".journal", "bin", "journal-hook")

    def run(self, asked: Asked) -> Answer:
        if asked.kind in FILE_KINDS:
            return self.file_step(asked)
        if asked.kind == "worktree":
            return self.worktree_step(asked)
        if asked.kind == "checkout":
            return self.checkout_step(asked)
        if asked.kind == "pid":
            return self.pid_step(asked)
        if asked.kind == "look":
            return self.look_step(asked)
        if asked.kind == "told":
            return self.told_step(asked)
        if asked.kind == "processes":
            return self.processes_step()
        if asked.kind == "stop-service":
            return self.stop_service_step(asked)
        if asked.kind == "wait-service":
            return self.wait_service_step(asked)
        if asked.kind == "throwaway":
            return self.throwaway_step(asked)
        if asked.kind == "memory-file":
            return self.memory_file_step(asked)
        argv, stdin, folder = self.command_of(asked), asked.stdin, self.folder_of(asked)
        environment = self.environment_of(asked)
        try:
            done = subprocess.run(argv, input=stdin, capture_output=True, text=True, cwd=folder, timeout=STEP_SECONDS,
                                  env=environment)
        except subprocess.TimeoutExpired:
            return Answer(124, "", f"the step ran past {STEP_SECONDS} seconds and was stopped\n")
        out = done.stdout
        if asked.kind == "hook":
            out += self.lines_named_in(done.stdout, environment)
        return Answer(done.returncode, out, done.stderr)

    def lines_named_in(self, answered: str, environment: dict) -> str:
        """A hook's answer names each line the journal has for the agent by its number, and a real agent's terminal
        shows the line itself; so the relay reads each named line as that agent, from the test checkout, whose folder
        is the one the journal knows as the test environment's, and hands its text back with it."""
        read = ""
        for number in re.findall(r"\d+", ",".join(AGENT_LINES.findall(answered))):
            shown = subprocess.run([self.journal, "agent-line", "show", number], capture_output=True, text=True,
                                   cwd=os.path.join(self.project, TEST_CHECKOUT), timeout=STEP_SECONDS, env=environment)
            read += shown.stdout + shown.stderr
        return read

    def pid_step(self, asked: Asked) -> Answer:
        """Hand out a process of its own, in a session of its own, for a fake agent to be registered with, or end one."""
        with self.holding:
            if asked.action == "end":
                self.end_sleeper(int(asked.pid))
                return Answer(0)
            sleeper = subprocess.Popen(SLEEPER, start_new_session=True, stdin=subprocess.DEVNULL,
                                       stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            self.sleepers[sleeper.pid] = sleeper
            return Answer(0, str(sleeper.pid))

    def end_sleeper(self, pid: int) -> None:
        sleeper = self.sleepers.pop(pid, None)
        if sleeper is None:
            return
        sleeper.terminate()
        sleeper.wait()

    def processes_step(self) -> Answer:
        """The plugin's own processes and every bridge left behind by a service that is gone."""
        running = running_processes()
        return Answer(0, "".join(process.line() for process in self.plugin_processes(running) + [each for each in running if each.is_orphaned_bridge]))

    def plugin_processes(self, running: list[Process]) -> list[Process]:
        """Every process started from this project's plugin folder and all it started in turn: a bridge names only the
        shared cache, so its parent is what makes it this project's."""
        folders = {os.path.join(self.project, PLUGINS), os.path.join(os.path.realpath(self.project), PLUGINS)}
        found = descendants_of({each.pid for each in running if any(folder in each.command for folder in folders)}, running)
        return [each for each in running if each.pid in found]

    def stop_service_step(self, asked: Asked) -> Answer:
        """Send a stopping signal to one of the plugin's processes, the list read again right before it, and to no other."""
        if asked.action not in STOPPING_SIGNALS:
            raise Refused(f"a service is stopped with one of {sorted(STOPPING_SIGNALS)}")
        pid = int(asked.pid)
        if pid not in {each.pid for each in self.plugin_processes(running_processes())}:
            raise Refused(f"{pid} is not a process of the plugin")
        os.kill(pid, STOPPING_SIGNALS[asked.action])
        return Answer(0)

    def wait_service_step(self, asked: Asked) -> Answer:
        """Wait until the journal says the named service runs, for `many` seconds at most, and answer with what it last said."""
        deadline = time.monotonic() + asked.many
        while True:
            done = subprocess.run([self.journal, "plugin", "services", "--json"], capture_output=True, text=True, cwd=self.project, timeout=STEP_SECONDS)
            rows = [row for row in json.loads(done.stdout or '{"rows": []}')["rows"] if row["service"] == asked.name]
            if rows and rows[0]["running"]:
                return Answer(0, json.dumps(rows[0]))
            if time.monotonic() >= deadline:
                return Answer(124, json.dumps(rows), f"the service {asked.name} was not running after {asked.many} seconds\n")
            time.sleep(BETWEEN_LOOKS_SECONDS)

    def throwaway_step(self, asked: Asked) -> Answer:
        """Start a throwaway service under a parent of its own, end such a parent, or tear every one of them down."""
        with self.holding:
            if asked.action == "start":
                return self.start_throwaway(asked.name)
            if asked.action == "end":
                return self.end_throwaway_parent(int(asked.pid))
            if asked.action == "teardown":
                return self.tear_down_throwaways()
            if asked.action == "freeze":
                return self.freeze_throwaway_child(int(asked.pid))
            if asked.action == "ask":
                return self.ask_throwaway(asked.path)
            if asked.action == "log":
                return self.log_of_throwaway(int(asked.pid))
        raise Refused(f"'{asked.action}' is not something a throwaway service does")

    def start_throwaway(self, kind: str) -> Answer:
        kind, _, bare = kind.partition(":")
        if kind not in SERVICE_VERBS:
            raise Refused(f"'{kind}' is none of {sorted(SERVICE_VERBS)}")
        folder = tempfile.mkdtemp(prefix="story-", dir="/tmp")
        project, data, health = os.path.join(folder, "project"), os.path.join(folder, "data"), os.path.join(folder, "health.sock")
        os.makedirs(project)
        os.makedirs(data)
        if kind in LANGUAGE_SAMPLES and not bare:
            name, source = LANGUAGE_SAMPLES[kind]
            written_whole(os.path.join(project, name), source)
        subprocess.run(["git", "init", "--quiet"], cwd=project, check=True)
        environment = {name: os.environ[name] for name in LAUNCH_VARIABLES if name in os.environ}
        environment.update({"COMMANDMENTS_HEALTH_SOCKET": health, "JOURNAL_PLUGIN_DATA": data, "JOURNAL_ENV": STORY_ENVIRONMENT, "JOURNAL_ROOT": project, "JOURNAL_PLUGIN_SOCKET": os.path.join(folder, "plugin.sock")})
        binary = os.path.join(self.project, PLUGIN_BINARY)
        parent = subprocess.Popen(["sh", "-c", UNDER_A_PARENT, "sh", binary, SERVICE_VERBS[kind]], stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
                                  stderr=subprocess.DEVNULL, text=True, cwd=project, env=environment, start_new_session=True)
        service = int(parent.stdout.readline())
        parent.stdout.close()
        self.throwaways[parent.pid] = Throwaway(parent, service, folder)
        return Answer(0, json.dumps({"pid": service, "parent": parent.pid, "socket": health, "data": data}))

    def freeze_throwaway_child(self, pid: int) -> Answer:
        """Stop a process a throwaway service started dead in its tracks, as a bridge that hangs is, until the teardown kills it."""
        services = {each.service for each in self.throwaways.values()}
        if pid in services or pid not in descendants_of(services, running_processes()):
            raise Refused(f"{pid} is not a process a throwaway service started")
        os.kill(pid, signal.SIGSTOP)
        self.frozen.add(pid)
        return Answer(0)

    def ask_throwaway(self, socket_path: str) -> Answer:
        """Ask a throwaway service whether it is up, over the health socket the journal would use."""
        if socket_path not in {os.path.join(each.folder, "health.sock") for each in self.throwaways.values()}:
            raise Refused(f"{socket_path} is no socket of a throwaway service")
        try:
            with socket.socket(socket.AF_UNIX) as connection:
                connection.settimeout(BETWEEN_LOOKS_SECONDS * 10)
                connection.connect(socket_path)
                return Answer(0, connection.recv(4096).decode())
        except OSError as unreachable:
            return Answer(1, "", f"the service did not answer: {unreachable}\n")

    def log_of_throwaway(self, parent: int) -> Answer:
        """Answer with everything a throwaway service wrote while it ran."""
        kept = self.throwaways.get(parent)
        if kept is None:
            raise Refused(f"{parent} is not a parent this relay started")
        with open(os.path.join(kept.folder, "service.log")) as written:
            return Answer(0, written.read())

    def end_throwaway_parent(self, pid: int) -> Answer:
        kept = self.throwaways.get(pid)
        if kept is None:
            raise Refused(f"{pid} is not a parent this relay started")
        kept.parent.kill()
        kept.parent.wait()
        return Answer(0)

    def tear_down_throwaways(self) -> Answer:
        """Kill every throwaway parent and service and all they started, and answer with any that would not go."""
        started = {pid for each in self.throwaways.values() for pid in (each.parent.pid, each.service)}
        targets = descendants_of(started, running_processes()) | self.frozen
        for pid in targets:
            try:
                os.kill(pid, signal.SIGKILL)
            except ProcessLookupError:
                pass
        for each in self.throwaways.values():
            each.parent.wait()
            shutil.rmtree(each.folder, ignore_errors=True)
        self.throwaways.clear()
        self.frozen.clear()
        deadline = time.monotonic() + TORN_DOWN_WITHIN_SECONDS
        while True:
            left = sorted(targets & {each.pid for each in running_processes()})
            if not left or time.monotonic() >= deadline:
                break
            time.sleep(BETWEEN_LOOKS_SECONDS)
        if left:
            return Answer(1, "", f"these throwaway processes outlived their teardown: {left}\n")
        return Answer(0)

    def memory_file_step(self, asked: Asked) -> Answer:
        """Read, age, stamp, replace or remove the project's saved detector memory, the one file this step can reach.
        A story ages it while the service is stopped, as it stops a service for a restart, so nothing writes it at once."""
        path = os.path.join(self.project, DETECTOR_MEMORY)
        if asked.action == "read":
            return Answer(0, json.dumps(reading_of(path)))
        if asked.action == "remove":
            if os.path.exists(path):
                os.remove(path)
            return Answer(0)
        if asked.action == "replace":
            written_whole(path, asked.stdin)
            return Answer(0)
        with open(path) as read:
            saved = json.load(read)
        if asked.action == "age":
            for verdict in verdicts_of(saved):
                verdict["Used"] -= asked.many
        elif asked.action == "stamp":
            saved["Build"] = asked.name
        else:
            raise Refused(f"'{asked.action}' is not something the memory file does")
        written_whole(path, json.dumps(saved))
        return Answer(0)

    def server_close(self) -> None:
        with self.holding:
            for pid in list(self.sleepers):
                self.end_sleeper(pid)
            self.tear_down_throwaways()
        super().server_close()

    def file_step(self, asked: Asked) -> Answer:
        """Write, link or remove one file under a scratch folder of the test checkout, the only place a story may change."""
        try:
            path = self.scratch_path(asked.path)
            if asked.kind == "write":
                os.makedirs(os.path.dirname(path), exist_ok=True)
                stem, extension = os.path.splitext(path)
                many = int(asked.many)
                for number in range(many) if many else [None]:
                    with open(path if number is None else f"{stem}_{number:04d}{extension}", "w") as written:
                        written.write(asked.stdin)
            elif asked.kind == "link":
                self.scratch_path(os.path.join(os.path.dirname(path), asked.stdin))
                os.makedirs(os.path.dirname(path), exist_ok=True)
                os.symlink(asked.stdin, path)
            elif os.path.isdir(path):
                shutil.rmtree(path)
            elif os.path.exists(path):
                os.remove(path)
        except Refused as refusal:
            return Answer.refused(refusal)
        except OSError as unable:
            return Answer(126, "", f"the story relay could not change the file: {unable}\n")
        return Answer(0)

    def scratch_path(self, named: str) -> str:
        path = os.path.realpath(named)
        worktrees = os.path.realpath(os.path.join(self.project, WORKTREES))
        checkout, _, under = os.path.relpath(path, worktrees).partition(os.sep)
        if not self.a_story_checkout(checkout) or not any(part.startswith(SCRATCH_PREFIX) for part in under.split(os.sep)):
            raise Refused(f"{named} is not inside a {SCRATCH_PREFIX}* folder of the test checkout or a story worktree")
        return path

    def a_story_checkout(self, name: str) -> bool:
        return name == os.path.basename(TEST_CHECKOUT) or name.startswith(WORKTREE_PREFIX)

    def a_dispatched_worker(self, folder: str) -> bool:
        cut_in = os.path.realpath(os.path.join(self.project, TEST_CHECKOUT, WORKTREES, STORY_ENVIRONMENT))
        return os.path.dirname(folder) == cut_in and os.path.basename(folder).startswith("worker-")

    def worktree_step(self, asked: Asked) -> Answer:
        """Cut or remove a worktree of the test checkout for a fake worker, named story-engine-*, beside the others."""
        folder = os.path.join(self.project, WORKTREES, asked.name)
        if not asked.name.startswith(WORKTREE_PREFIX) or os.sep in asked.name:
            return Answer.refused(f"worktrees are named {WORKTREE_PREFIX}*")
        checkout = os.path.join(self.project, TEST_CHECKOUT)
        if asked.action == "rebase":
            branch = subprocess.run(["git", "-C", checkout, "rev-parse", "--abbrev-ref", "HEAD"], capture_output=True, text=True).stdout.strip()
            done = subprocess.run(["git", "-C", folder, "rebase", branch], capture_output=True, text=True, timeout=STEP_SECONDS)
            return Answer.of(done)
        if asked.action == "cut":
            shutil.rmtree(self.scan_state_of(folder), ignore_errors=True)
            shutil.rmtree(self.worklist_of(folder), ignore_errors=True)
            subprocess.run(["git", "-C", checkout, "worktree", "remove", "--force", folder], capture_output=True, text=True)
            shutil.rmtree(folder, ignore_errors=True)
            subprocess.run(["git", "-C", checkout, "worktree", "prune"], capture_output=True, text=True)
            argv = ["git", "-C", checkout, "worktree", "add", "-q", "-B", asked.name, folder, "HEAD"]
        else:
            argv = ["git", "-C", checkout, "worktree", "remove", "--force", folder]
        done = subprocess.run(argv, capture_output=True, text=True, timeout=STEP_SECONDS)
        if asked.action != "cut" and done.returncode == 0:
            subprocess.run(["git", "-C", checkout, "branch", "-D", asked.name], capture_output=True, text=True)
        return Answer.of(done)

    def told_step(self, asked: Asked) -> Answer:
        """The lines the journal holds for a story's worker, which can no longer ask for them itself once its merge ended it."""
        if asked.actor is None or not re.fullmatch(r"[0-9]+", asked.actor):
            return Answer.refused("a worker is named by its row")
        store = sqlite3.connect(f"file:{os.path.join(self.project, JOURNAL_STORE)}?mode=ro", uri=True)
        try:
            lines = store.execute("SELECT text FROM agent_lines WHERE session_agent_id = ? ORDER BY id", [int(asked.actor)]).fetchall()
        finally:
            store.close()
        return Answer(0, "".join(line[0] + "\n" for line in lines))

    def look_step(self, asked: Asked) -> Answer:
        """Let the journal's file scan take its look at a story checkout under a session no agent has: it records
        what the checkout holds now and hands nothing to anyone, so only what a story's agent writes afterwards is
        that agent's edit."""
        folder = os.path.realpath(asked.folder)
        worktrees = os.path.realpath(os.path.join(self.project, WORKTREES))
        checkout, _, _ = os.path.relpath(folder, worktrees).partition(os.sep)
        beside_the_others = self.a_story_checkout(checkout) and os.path.dirname(folder) == worktrees
        if not beside_the_others and not self.a_dispatched_worker(folder):
            raise Refused(f"{asked.folder} is neither the test checkout nor a story worktree")
        done = subprocess.run([self.hook, "--file-scan", os.path.join(self.project, ".journal"), "--session", NO_AGENT, folder],
                              capture_output=True, text=True, cwd=folder, timeout=STEP_SECONDS)
        return Answer.of(done)

    def scan_state_of(self, folder: str) -> str:
        """What the journal's file scan kept of a checkout at this folder: a story's worktree is new each time, so
        what a stopped run left here would hide its first edits."""
        return os.path.join(self.project, ".journal", "runtime", "file-scan", hashlib.sha256(folder.encode()).hexdigest()[:16])

    def worklist_of(self, folder: str) -> str:
        """The worklist the plugin keeps for a checkout at this folder: a story's worktree is new each time, so what
        a story before it left open here is not this one's."""
        key = hashlib.sha1(f"checkout:{folder}".encode()).hexdigest()[:5]
        return os.path.join(self.project, ".journal", "plugin-data", "code-commandments", "sessions", key, "sins")

    def checkout_step(self, asked: Asked) -> Answer:
        """Move the test checkout off its branch, onto another branch or a detached head, and back to where it was."""
        checkout = os.path.join(self.project, TEST_CHECKOUT)
        if asked.action in ("tip", "reset"):
            if asked.action == "reset" and (asked.name is None or not re.fullmatch(r"[0-9a-f]{40}", asked.name)):
                return Answer.refused("a reset names a whole commit")
            argv = ["git", "-C", checkout, "rev-parse", "HEAD"] if asked.action == "tip" else ["git", "-C", checkout, "reset", "-q", "--hard", asked.name]
            done = subprocess.run(argv, capture_output=True, text=True, timeout=STEP_SECONDS)
            if asked.action == "reset" and done.returncode == 0:
                self.forgets_the_merges_judged_on(checkout)
            return Answer.of(done)
        if asked.action == "back":
            argv = ["git", "-C", checkout, "switch", "-q", self.home] if self.home else ["true"]
        elif self.home:
            return Answer(0)
        else:
            self.home = subprocess.run(["git", "-C", checkout, "rev-parse", "--abbrev-ref", "HEAD"], capture_output=True, text=True).stdout.strip()
            argv = ["git", "-C", checkout, "switch", "-q", "--detach"] if asked.action == "detach" else ["git", "-C", checkout, "switch", "-q", "-C", BRANCH_PREFIX + "other", "HEAD"]
        done = subprocess.run(argv, capture_output=True, text=True, timeout=STEP_SECONDS)
        if asked.action == "back" and done.returncode == 0:
            self.home = ""
            subprocess.run(["git", "-C", checkout, "branch", "-D", BRANCH_PREFIX + "other"], capture_output=True, text=True)
        return Answer.of(done)

    def forgets_the_merges_judged_on(self, checkout: str) -> None:
        """A branch put back leaves the plugin's mark on a commit it no longer holds, which the next merge would rightly report."""
        mark = os.path.join(self.project, MERGES_JUDGED)
        if not os.path.exists(mark):
            return
        branch = subprocess.run(["git", "-C", checkout, "rev-parse", "--abbrev-ref", "HEAD"], capture_output=True, text=True).stdout.strip()
        with open(mark) as read:
            kept = [line for line in read if not line.startswith(branch + "\t")]
        with open(mark, "w") as written:
            written.writelines(kept)

    def environment_of(self, asked: Asked) -> dict[str, str]:
        """What a step runs with. A hook or command sent for a story's agent runs with the variables a fresh launch of that agent
        has, and none of the session that started the relay, whose own variables would make the journal take the step
        as that session's."""
        if asked.kind not in ("hook", "command") or asked.actor is None:
            return dict(os.environ)
        environment = {name: os.environ[name] for name in LAUNCH_VARIABLES if name in os.environ}
        environment.update({"AGENT_JOURNAL_ACTIVE": "1", "JOURNAL_ACTOR": f"{asked.role}:{asked.actor}",
                            "JOURNAL_ENV": STORY_ENVIRONMENT, "JOURNAL_ROLE": asked.role})
        if asked.session is not None:
            environment["JOURNAL_AGENT_SESSION"] = asked.session
        return environment

    def command_of(self, asked: Asked) -> list[str]:
        if asked.kind == "hook":
            return [self.hook, "claude", self.project]
        argv = asked.argv
        if not argv or argv[0] not in NOUNS:
            raise Refused(f"'{argv[0] if argv else ''}' is not one of the journal nouns a story may run")
        if argv[:2] == ["agent", "start"] and "main" in argv[2:5]:
            raise Refused("a story never starts a main agent: the journal can place it in the person's own "
                          "environment, where a new main agent ends the real one and its terminal")
        return [self.journal, *argv]

    def folder_of(self, asked: Asked) -> str:
        folder = os.path.realpath(self.project if asked.folder is None else asked.folder)
        if os.path.commonpath([folder, os.path.realpath(self.project)]) != os.path.realpath(self.project):
            raise Refused(f"{folder} is outside the project")
        return folder


def running_processes() -> list[Process]:
    listed = subprocess.run(["ps", "-axo", "pid=,ppid=,stat=,command="], capture_output=True, text=True, timeout=STEP_SECONDS).stdout
    fields = (line.split(None, 3) for line in listed.splitlines())
    return [Process(int(each[0]), int(each[1]), each[3]) for each in fields if len(each) == 4 and "Z" not in each[2]]


def verdicts_of(saved: dict) -> list[dict]:
    return [verdict for verdicts in (saved["Detectors"] or {}).values() for verdict in verdicts]


def reading_of(path: str) -> dict:
    """What the saved detector memory holds now, in the shape cli/hooks/remembered_detectors.go writes it."""
    if not os.path.exists(path):
        return {"present": False}
    size = os.path.getsize(path)
    try:
        with open(path) as read:
            saved = json.load(read)
        verdicts = verdicts_of(saved)
    except (ValueError, KeyError, TypeError, AttributeError):
        return {"present": True, "readable": False, "size": size}
    used = [verdict["Used"] for verdict in verdicts]
    return {"present": True, "readable": True, "size": size, "entries": len(verdicts), "build": saved["Build"],
            "oldest": min(used, default=0), "newest": max(used, default=0)}


def written_whole(path: str, text: str) -> None:
    draft = f"{path}.{os.getpid()}"
    with open(draft, "w") as written:
        written.write(text)
    os.replace(draft, path)


def descendants_of(roots: set[int], running: list[Process]) -> set[int]:
    found = set(roots)
    while children := {each.pid for each in running if each.parent in found} - found:
        found |= children
    return found


def main() -> None:
    project, port_file = sys.argv[1], sys.argv[2]
    relay = Relay(project)
    with open(port_file, "w") as written:
        written.write(str(relay.server_address[1]))
    signal.signal(signal.SIGTERM, lambda number, frame: sys.exit(0))
    try:
        relay.serve_forever()
    finally:
        relay.server_close()


if __name__ == "__main__":
    main()
