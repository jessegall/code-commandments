import json
import os
import socketserver
import subprocess
import sys

NOUNS = frozenset({
    "agent",
    "commandments-judge-changes",
    "environment",
    "file-edit",
    "output",
    "plugin",
    "sins",
    "task",
    "worker",
    "worker-worktree",
})

STEP_SECONDS = 600


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
        self.journal = os.path.join(project, ".journal", "bin", "journal")
        self.hook = os.path.join(project, ".journal", "bin", "journal-hook")

    def run(self, asked: dict) -> dict:
        argv, stdin, folder = self.command_of(asked), asked.get("stdin", ""), self.folder_of(asked)
        try:
            done = subprocess.run(argv, input=stdin, capture_output=True, text=True, cwd=folder, timeout=STEP_SECONDS)
        except subprocess.TimeoutExpired:
            return {"code": 124, "out": "", "err": f"the step ran past {STEP_SECONDS} seconds and was stopped\n"}
        return {"code": done.returncode, "out": done.stdout, "err": done.stderr}

    def command_of(self, asked: dict) -> list[str]:
        if asked["kind"] == "hook":
            return [self.hook, "claude", self.project]
        argv = asked["argv"]
        if not argv or argv[0] not in NOUNS:
            raise Refused(f"'{argv[0] if argv else ''}' is not one of the journal nouns a story may run")
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
    relay.serve_forever()


if __name__ == "__main__":
    main()
