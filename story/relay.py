import hashlib
import json
import re
import os
import shutil
import signal
import socketserver
import subprocess
import sys
import threading

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
    "rules",
    "sins",
    "task",
    "worker",
    "worker-worktree",
})

STEP_SECONDS = 600

FILE_KINDS = frozenset({"write", "remove"})
WORKTREES = os.path.join(".journal", "worktrees")
TEST_CHECKOUT = os.path.join(WORKTREES, "story-engine")
SCRATCH_PREFIX = "story_scratch"
WORKTREE_PREFIX = "story-engine-"
SLEEPER = ["sleep", "86400"]
BRANCH_PREFIX = "story-engine/story-"
STORY_ENVIRONMENT = "story-engine"
NO_AGENT = "story-engine-no-agent"
LAUNCH_VARIABLES = ("HOME", "PATH", "USER", "LOGNAME", "SHELL", "LANG", "LC_ALL", "TMPDIR", "TERM")
AGENT_LINES = re.compile(r"agent-lines?[: ]\s*(\d+(?:(?:,\s*|\s+and\s+)\d+)*)")


class Refused(Exception):
    """A request the relay will not run, with the reason it is told."""


class Step(socketserver.StreamRequestHandler):
    """One request: a journal command or a hook, run on this machine, answered with its output and exit code."""

    def handle(self) -> None:
        try:
            asked = json.loads(self.rfile.readline())
            answer = self.server.run(asked)
        except Refused as refusal:
            answer = {"code": 126, "out": "", "err": f"the story relay refuses this: {refusal}\n"}
        except (ValueError, KeyError) as broken:
            answer = {"code": 126, "out": "", "err": f"the story relay could not read the request: {broken}\n"}
        self.wfile.write((json.dumps(answer) + "\n").encode())


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
        self.holding = threading.Lock()
        self.journal = os.path.join(project, ".journal", "bin", "journal")
        self.hook = os.path.join(project, ".journal", "bin", "journal-hook")

    def run(self, asked: dict) -> dict:
        if asked["kind"] in FILE_KINDS:
            return self.file_step(asked)
        if asked["kind"] == "worktree":
            return self.worktree_step(asked)
        if asked["kind"] == "checkout":
            return self.checkout_step(asked)
        if asked["kind"] == "pid":
            return self.pid_step(asked)
        if asked["kind"] == "look":
            return self.look_step(asked)
        argv, stdin, folder = self.command_of(asked), asked.get("stdin", ""), self.folder_of(asked)
        environment = self.environment_of(asked)
        try:
            done = subprocess.run(argv, input=stdin, capture_output=True, text=True, cwd=folder, timeout=STEP_SECONDS,
                                  env=environment)
        except subprocess.TimeoutExpired:
            return {"code": 124, "out": "", "err": f"the step ran past {STEP_SECONDS} seconds and was stopped\n"}
        out = done.stdout
        if asked["kind"] == "hook":
            out += self.lines_named_in(done.stdout, environment)
        return {"code": done.returncode, "out": out, "err": done.stderr}

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

    def pid_step(self, asked: dict) -> dict:
        """Hand out a process of its own, in a session of its own, for a fake agent to be registered with, or end one."""
        with self.holding:
            if asked.get("action") == "end":
                self.end_sleeper(int(asked["pid"]))
                return {"code": 0, "out": "", "err": ""}
            sleeper = subprocess.Popen(SLEEPER, start_new_session=True, stdin=subprocess.DEVNULL,
                                       stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            self.sleepers[sleeper.pid] = sleeper
            return {"code": 0, "out": str(sleeper.pid), "err": ""}

    def end_sleeper(self, pid: int) -> None:
        sleeper = self.sleepers.pop(pid, None)
        if sleeper is None:
            return
        sleeper.terminate()
        sleeper.wait()

    def server_close(self) -> None:
        with self.holding:
            for pid in list(self.sleepers):
                self.end_sleeper(pid)
        super().server_close()

    def file_step(self, asked: dict) -> dict:
        """Write or remove one file under a scratch folder of the test checkout, the only place a story may change."""
        try:
            path = self.scratch_path(asked["path"])
            if asked["kind"] == "write":
                os.makedirs(os.path.dirname(path), exist_ok=True)
                stem, extension = os.path.splitext(path)
                many = int(asked.get("many", 0))
                for number in range(many) if many else [None]:
                    with open(path if number is None else f"{stem}_{number:04d}{extension}", "w") as written:
                        written.write(asked.get("stdin", ""))
            elif os.path.isdir(path):
                shutil.rmtree(path)
            elif os.path.exists(path):
                os.remove(path)
        except Refused as refusal:
            return {"code": 126, "out": "", "err": f"the story relay refuses this: {refusal}\n"}
        except OSError as unable:
            return {"code": 126, "out": "", "err": f"the story relay could not change the file: {unable}\n"}
        return {"code": 0, "out": "", "err": ""}

    def scratch_path(self, named: str) -> str:
        path = os.path.realpath(named)
        worktrees = os.path.realpath(os.path.join(self.project, WORKTREES))
        checkout, _, under = os.path.relpath(path, worktrees).partition(os.sep)
        if not self.a_story_checkout(checkout) or not any(part.startswith(SCRATCH_PREFIX) for part in under.split(os.sep)):
            raise Refused(f"{named} is not inside a {SCRATCH_PREFIX}* folder of the test checkout or a story worktree")
        return path

    def a_story_checkout(self, name: str) -> bool:
        return name == os.path.basename(TEST_CHECKOUT) or name.startswith(WORKTREE_PREFIX)

    def worktree_step(self, asked: dict) -> dict:
        """Cut or remove a worktree of the test checkout for a fake worker, named story-engine-*, beside the others."""
        folder = os.path.join(self.project, WORKTREES, asked["name"])
        if not asked["name"].startswith(WORKTREE_PREFIX) or os.sep in asked["name"]:
            return {"code": 126, "out": "", "err": f"the story relay refuses this: worktrees are named {WORKTREE_PREFIX}*\n"}
        checkout = os.path.join(self.project, TEST_CHECKOUT)
        if asked["action"] == "rebase":
            branch = subprocess.run(["git", "-C", checkout, "rev-parse", "--abbrev-ref", "HEAD"], capture_output=True, text=True).stdout.strip()
            done = subprocess.run(["git", "-C", folder, "rebase", branch], capture_output=True, text=True, timeout=STEP_SECONDS)
            return {"code": done.returncode, "out": done.stdout, "err": done.stderr}
        if asked["action"] == "cut":
            shutil.rmtree(self.scan_state_of(folder), ignore_errors=True)
            shutil.rmtree(self.worklist_of(folder), ignore_errors=True)
            subprocess.run(["git", "-C", checkout, "worktree", "remove", "--force", folder], capture_output=True, text=True)
            shutil.rmtree(folder, ignore_errors=True)
            subprocess.run(["git", "-C", checkout, "worktree", "prune"], capture_output=True, text=True)
            argv = ["git", "-C", checkout, "worktree", "add", "-q", "-B", asked["name"], folder, "HEAD"]
        else:
            argv = ["git", "-C", checkout, "worktree", "remove", "--force", folder]
        done = subprocess.run(argv, capture_output=True, text=True, timeout=STEP_SECONDS)
        if asked["action"] != "cut" and done.returncode == 0:
            subprocess.run(["git", "-C", checkout, "branch", "-D", asked["name"]], capture_output=True, text=True)
        return {"code": done.returncode, "out": done.stdout, "err": done.stderr}

    def look_step(self, asked: dict) -> dict:
        """Let the journal's file scan take its look at a story checkout under a session no agent has: it records
        what the checkout holds now and hands nothing to anyone, so only what a story's agent writes afterwards is
        that agent's edit."""
        folder = os.path.realpath(asked["in"])
        worktrees = os.path.realpath(os.path.join(self.project, WORKTREES))
        checkout, _, _ = os.path.relpath(folder, worktrees).partition(os.sep)
        if not self.a_story_checkout(checkout) or os.path.dirname(folder) != worktrees:
            raise Refused(f"{asked['in']} is neither the test checkout nor a story worktree")
        done = subprocess.run([self.hook, "--file-scan", os.path.join(self.project, ".journal"), "--session", NO_AGENT, folder],
                              capture_output=True, text=True, cwd=folder, timeout=STEP_SECONDS)
        return {"code": done.returncode, "out": done.stdout, "err": done.stderr}

    def scan_state_of(self, folder: str) -> str:
        """What the journal's file scan kept of a checkout at this folder: a story's worktree is new each time, so
        what a stopped run left here would hide its first edits."""
        return os.path.join(self.project, ".journal", "runtime", "file-scan", hashlib.sha256(folder.encode()).hexdigest()[:16])

    def worklist_of(self, folder: str) -> str:
        """The worklist the plugin keeps for a checkout at this folder: a story's worktree is new each time, so what
        a story before it left open here is not this one's."""
        key = hashlib.sha1(f"checkout:{folder}".encode()).hexdigest()[:5]
        return os.path.join(self.project, ".journal", "plugin-data", "code-commandments", "sessions", key, "sins")

    def checkout_step(self, asked: dict) -> dict:
        """Move the test checkout off its branch, onto another branch or a detached head, and back to where it was."""
        checkout = os.path.join(self.project, TEST_CHECKOUT)
        if asked["action"] == "back":
            argv = ["git", "-C", checkout, "switch", "-q", self.home] if self.home else ["true"]
        elif self.home:
            return {"code": 0, "out": "", "err": ""}
        else:
            self.home = subprocess.run(["git", "-C", checkout, "rev-parse", "--abbrev-ref", "HEAD"], capture_output=True, text=True).stdout.strip()
            argv = ["git", "-C", checkout, "switch", "-q", "--detach"] if asked["action"] == "detach" else ["git", "-C", checkout, "switch", "-q", "-C", BRANCH_PREFIX + "other", "HEAD"]
        done = subprocess.run(argv, capture_output=True, text=True, timeout=STEP_SECONDS)
        if asked["action"] == "back" and done.returncode == 0:
            self.home = ""
            subprocess.run(["git", "-C", checkout, "branch", "-D", BRANCH_PREFIX + "other"], capture_output=True, text=True)
        return {"code": done.returncode, "out": done.stdout, "err": done.stderr}

    def environment_of(self, asked: dict) -> dict:
        """What a step runs with. A hook or command sent for a story's agent runs with the variables a fresh launch of that agent
        has, and none of the session that started the relay, whose own variables would make the journal take the step
        as that session's."""
        if asked["kind"] not in ("hook", "command") or not asked.get("actor"):
            return dict(os.environ)
        environment = {name: os.environ[name] for name in LAUNCH_VARIABLES if name in os.environ}
        role = asked.get("role") or "worker"
        environment.update({"AGENT_JOURNAL_ACTIVE": "1", "JOURNAL_ACTOR": f"{role}:{asked['actor']}",
                            "JOURNAL_ENV": STORY_ENVIRONMENT, "JOURNAL_ROLE": role})
        return environment

    def command_of(self, asked: dict) -> list[str]:
        if asked["kind"] == "hook":
            return [self.hook, "claude", self.project]
        argv = asked["argv"]
        if not argv or argv[0] not in NOUNS:
            raise Refused(f"'{argv[0] if argv else ''}' is not one of the journal nouns a story may run")
        if argv[:2] == ["agent", "start"] and "main" in argv[2:5]:
            raise Refused("a story never starts a main agent: the journal can place it in the person's own "
                          "environment, where a new main agent ends the real one and its terminal")
        return [self.journal, *argv]

    def folder_of(self, asked: dict) -> str:
        folder = os.path.realpath(asked.get("in") or self.project)
        if os.path.commonpath([folder, os.path.realpath(self.project)]) != os.path.realpath(self.project):
            raise Refused(f"{folder} is outside the project")
        return folder


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
