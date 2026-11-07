import json
import os
import shutil
import socketserver
import subprocess
import sys

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
RELAY_PID = "{relay-pid}"
BRANCH_PREFIX = "story-engine/story-"


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
        self.journal = os.path.join(project, ".journal", "bin", "journal")
        self.hook = os.path.join(project, ".journal", "bin", "journal-hook")

    def run(self, asked: dict) -> dict:
        if asked["kind"] in FILE_KINDS:
            return self.file_step(asked)
        if asked["kind"] == "worktree":
            return self.worktree_step(asked)
        if asked["kind"] == "checkout":
            return self.checkout_step(asked)
        argv, stdin, folder = self.command_of(asked), asked.get("stdin", ""), self.folder_of(asked)
        try:
            done = subprocess.run(argv, input=stdin, capture_output=True, text=True, cwd=folder, timeout=STEP_SECONDS)
        except subprocess.TimeoutExpired:
            return {"code": 124, "out": "", "err": f"the step ran past {STEP_SECONDS} seconds and was stopped\n"}
        return {"code": done.returncode, "out": done.stdout, "err": done.stderr}

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
        checkout = os.path.realpath(os.path.join(self.project, TEST_CHECKOUT))
        under = os.path.relpath(path, checkout)
        if under.startswith("..") or not any(part.startswith(SCRATCH_PREFIX) for part in under.split(os.sep)):
            raise Refused(f"{named} is not inside a {SCRATCH_PREFIX}* folder of the test checkout")
        return path

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
            argv = ["git", "-C", checkout, "worktree", "add", "-q", "-b", asked["name"], folder, "HEAD"]
        else:
            argv = ["git", "-C", checkout, "worktree", "remove", "--force", folder]
        done = subprocess.run(argv, capture_output=True, text=True, timeout=STEP_SECONDS)
        if asked["action"] != "cut" and done.returncode == 0:
            subprocess.run(["git", "-C", checkout, "branch", "-D", asked["name"]], capture_output=True, text=True)
        return {"code": done.returncode, "out": done.stdout, "err": done.stderr}

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

    def command_of(self, asked: dict) -> list[str]:
        if asked["kind"] == "hook":
            return [self.hook, "claude", self.project]
        argv = asked["argv"]
        if not argv or argv[0] not in NOUNS:
            raise Refused(f"'{argv[0] if argv else ''}' is not one of the journal nouns a story may run")
        return [self.journal, *[str(os.getpid()) if word == RELAY_PID else word for word in argv]]

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
    relay.serve_forever()


if __name__ == "__main__":
    main()
