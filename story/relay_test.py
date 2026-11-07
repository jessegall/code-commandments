import json
import os
import socket
import stat
import tempfile
import threading
import unittest

from relay import Relay


class RelayTest(unittest.TestCase):
    """The relay runs only the journal commands a story may run, in the project, and hands back what they printed."""

    def setUp(self) -> None:
        self.folder = tempfile.TemporaryDirectory()
        self.project = os.path.realpath(self.folder.name)
        programs = os.path.join(self.project, ".journal", "bin")
        os.makedirs(programs)
        for name, says in (("journal", 'echo "journal $*"; pwd >&2'), ("journal-hook", 'echo "hook $*"; cat')):
            path = os.path.join(programs, name)
            with open(path, "w") as program:
                program.write(f"#!/bin/sh\n{says}\n")
            os.chmod(path, os.stat(path).st_mode | stat.S_IEXEC)
        self.relay = Relay(self.project)
        threading.Thread(target=self.relay.serve_forever, daemon=True).start()

    def tearDown(self) -> None:
        self.relay.shutdown()
        self.relay.server_close()
        self.folder.cleanup()

    def ask(self, asked: dict) -> dict:
        with socket.create_connection(self.relay.server_address) as connection:
            connection.sendall((json.dumps(asked) + "\n").encode())
            return json.loads(connection.makefile().readline())

    def test_a_command_runs_in_the_folder_the_story_names(self) -> None:
        worktree = os.path.join(self.project, "worktree")
        os.makedirs(worktree)
        ran = self.ask({"kind": "command", "argv": ["environment", "list"], "in": worktree})
        self.assertEqual(ran["code"], 0)
        self.assertEqual(ran["out"], "journal environment list\n")
        self.assertEqual(ran["err"].strip(), worktree)

    def test_a_hook_reaches_the_journals_hook_entry_with_its_body(self) -> None:
        ran = self.ask({"kind": "hook", "stdin": '{"hook_event_name": "Stop"}'})
        self.assertEqual(ran["out"], f'hook claude {self.project}\n{{"hook_event_name": "Stop"}}')

    def test_the_relays_own_process_number_stands_in_for_a_fake_agents_process(self) -> None:
        ran = self.ask({"kind": "command", "argv": ["agent", "start", "x", "claude", "main", "--pid", "{relay-pid}"]})
        self.assertIn(f"--pid {os.getpid()}", ran["out"])

    def test_a_noun_outside_the_list_is_refused_and_never_run(self) -> None:
        ran = self.ask({"kind": "command", "argv": ["rule", "make", "anything"]})
        self.assertEqual(ran["code"], 126)
        self.assertIn("is not one of the journal nouns", ran["err"])
        self.assertEqual(ran["out"], "")

    def test_a_folder_outside_the_project_is_refused(self) -> None:
        ran = self.ask({"kind": "command", "argv": ["environment", "list"], "in": "/"})
        self.assertEqual(ran["code"], 126)
        self.assertIn("outside the project", ran["err"])

    def scratch(self, *parts: str) -> str:
        checkout = os.path.join(self.project, ".journal", "worktrees", "story-engine")
        os.makedirs(checkout, exist_ok=True)
        return os.path.join(checkout, *parts)

    def test_a_file_is_written_and_removed_inside_a_scratch_folder(self) -> None:
        path = self.scratch("bridge", "mypy", "story_scratch_a", "order.py")
        self.assertEqual(self.ask({"kind": "write", "path": path, "stdin": "x = 1\n"})["code"], 0)
        with open(path) as written:
            self.assertEqual(written.read(), "x = 1\n")
        self.assertEqual(self.ask({"kind": "remove", "path": os.path.dirname(path)})["code"], 0)
        self.assertFalse(os.path.exists(os.path.dirname(path)))

    def test_a_file_outside_a_scratch_folder_is_refused_and_not_written(self) -> None:
        path = self.scratch("bridge", "mypy", "session.py")
        ran = self.ask({"kind": "write", "path": path, "stdin": "x = 1\n"})
        self.assertEqual(ran["code"], 126)
        self.assertFalse(os.path.exists(path))

    def test_a_file_outside_the_test_checkout_is_refused(self) -> None:
        path = os.path.join(self.project, "story_scratch_a", "order.py")
        self.assertEqual(self.ask({"kind": "write", "path": path, "stdin": ""})["code"], 126)
        self.assertFalse(os.path.exists(path))

    def test_a_worktree_not_named_for_the_stories_is_refused(self) -> None:
        ran = self.ask({"kind": "worktree", "action": "cut", "name": "main"})
        self.assertEqual(ran["code"], 126)
        self.assertIn("worktrees are named", ran["err"])

    def test_a_request_that_is_not_json_is_answered_not_dropped(self) -> None:
        with socket.create_connection(self.relay.server_address) as connection:
            connection.sendall(b"not json\n")
            ran = json.loads(connection.makefile().readline())
        self.assertEqual(ran["code"], 126)


if __name__ == "__main__":
    unittest.main()
