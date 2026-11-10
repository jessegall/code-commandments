import json
import os
import signal
import socket
import stat
import subprocess
import tempfile
import threading
import time
import unittest

from relay import Process, Relay


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

    def plugin_service(self) -> subprocess.Popen:
        folder = os.path.join(self.project, ".journal", "plugins", "code-commandments")
        os.makedirs(folder, exist_ok=True)
        path = os.path.join(folder, "serve")
        with open(path, "w") as program:
            program.write("#!/bin/sh\nsleep 86400\nexit 0\n")
        os.chmod(path, os.stat(path).st_mode | stat.S_IEXEC)
        service = subprocess.Popen([path], start_new_session=True)
        self.addCleanup(self.ends_the_group_of, service)
        self.waits_until(lambda: len(self.listed()) == 2)
        return service

    def ends_the_group_of(self, service: subprocess.Popen) -> None:
        try:
            os.killpg(service.pid, signal.SIGKILL)
        except ProcessLookupError:
            pass
        service.wait()

    def sleeper(self) -> subprocess.Popen:
        sleeper = subprocess.Popen(["sleep", "86400"])
        self.addCleanup(sleeper.wait)
        self.addCleanup(sleeper.kill)
        return sleeper

    def listed(self) -> list[list[str]]:
        return [line.split("\t") for line in self.ask({"kind": "processes"})["out"].splitlines()]

    def waits_until(self, holds) -> None:
        deadline = time.monotonic() + 5
        while not holds() and time.monotonic() < deadline:
            time.sleep(0.05)
        self.assertTrue(holds())

    def services_say(self, running: str) -> None:
        with open(os.path.join(self.project, ".journal", "bin", "journal"), "w") as program:
            program.write("#!/bin/sh\necho '{\"rows\": [{\"service\": \"hooks\", \"running\": " + running + "}]}'\n")

    def test_the_processes_step_lists_the_plugins_process_and_its_child_and_nothing_else(self) -> None:
        service = self.plugin_service()
        sleeper = self.sleeper()
        listed = self.listed()
        pids = [int(each[0]) for each in listed]
        self.assertIn(service.pid, pids)
        self.assertNotIn(sleeper.pid, pids)
        child = next(each for each in listed if int(each[0]) != service.pid)
        self.assertEqual(int(child[1]), service.pid)

    def test_a_bridge_whose_parent_is_gone_counts_as_an_orphan_and_one_with_a_parent_does_not(self) -> None:
        command = "/home/x/.cache/code-commandments/frontend/abc/bridge.mjs --serve"
        self.assertTrue(Process(10, 1, command).is_orphaned_bridge)
        self.assertFalse(Process(10, 77, command).is_orphaned_bridge)
        self.assertFalse(Process(10, 1, "/usr/sbin/cron").is_orphaned_bridge)

    def test_a_listed_process_is_stopped(self) -> None:
        service = self.plugin_service()
        answer = self.ask({"kind": "stop-service", "pid": str(service.pid), "action": "SIGTERM"})
        self.assertEqual(answer["code"], 0, answer["err"])
        service.wait(timeout=5)

    def test_a_process_that_is_not_the_plugins_is_refused_and_lives_on(self) -> None:
        sleeper = self.sleeper()
        answer = self.ask({"kind": "stop-service", "pid": str(sleeper.pid), "action": "SIGKILL"})
        self.assertEqual(answer["code"], 126)
        self.assertIsNone(sleeper.poll())

    def test_a_signal_that_does_not_stop_is_refused(self) -> None:
        service = self.plugin_service()
        answer = self.ask({"kind": "stop-service", "pid": str(service.pid), "action": "SIGHUP"})
        self.assertEqual(answer["code"], 126)
        self.assertIsNone(service.poll())

    def test_waiting_for_a_running_service_answers_at_once(self) -> None:
        self.services_say("true")
        answer = self.ask({"kind": "wait-service", "name": "hooks", "many": 5})
        self.assertEqual(answer["code"], 0, answer["err"])
        self.assertIn('"running": true', answer["out"])

    def test_waiting_for_a_service_that_never_runs_stops_at_the_deadline(self) -> None:
        self.services_say("false")
        answer = self.ask({"kind": "wait-service", "name": "hooks", "many": 1})
        self.assertEqual(answer["code"], 124)
        self.assertIn("hooks", answer["err"])

    def throwaway(self) -> dict:
        path = os.path.join(self.project, ".journal", "plugins", "code-commandments", "bin", "commandments-release")
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "w") as program:
            program.write("#!/bin/sh\nsleep 86400\nexit 0\n")
        os.chmod(path, os.stat(path).st_mode | stat.S_IEXEC)
        answer = self.ask({"kind": "throwaway", "action": "start", "name": "hooks"})
        self.assertEqual(answer["code"], 0, answer["err"])
        return json.loads(answer["out"])

    def alive(self, pid: int) -> bool:
        return any(int(each[0]) == pid for each in self.listed())

    def test_a_throwaway_service_is_listed_under_its_own_parent_in_its_own_folder(self) -> None:
        started = self.throwaway()
        self.waits_until(lambda: self.alive(started["pid"]))
        service = next(each for each in self.listed() if int(each[0]) == started["pid"])
        self.assertEqual(int(service[1]), started["parent"])
        self.assertTrue(started["socket"].startswith("/tmp/story-"))
        self.assertLess(len(started["socket"]), 104)
        self.assertEqual(self.ask({"kind": "throwaway", "action": "teardown"})["code"], 0)

    def test_ending_a_throwaway_parent_leaves_the_service_to_notice_for_itself(self) -> None:
        started = self.throwaway()
        self.waits_until(lambda: self.alive(started["pid"]))
        self.assertEqual(self.ask({"kind": "throwaway", "action": "end", "pid": str(started["parent"])})["code"], 0)
        self.assertTrue(self.alive(started["pid"]))
        self.assertEqual(self.ask({"kind": "throwaway", "action": "teardown"})["code"], 0)

    def test_a_parent_the_relay_did_not_start_is_refused_and_lives_on(self) -> None:
        sleeper = self.sleeper()
        answer = self.ask({"kind": "throwaway", "action": "end", "pid": str(sleeper.pid)})
        self.assertEqual(answer["code"], 126)
        self.assertIsNone(sleeper.poll())

    def test_a_teardown_leaves_no_throwaway_process_or_folder_behind(self) -> None:
        started = self.throwaway()
        self.waits_until(lambda: self.alive(started["pid"]))
        self.assertEqual(self.ask({"kind": "throwaway", "action": "teardown"})["code"], 0)
        self.waits_until(lambda: not self.alive(started["pid"]))
        self.assertFalse(os.path.exists(os.path.dirname(started["socket"])))

    def test_a_kind_of_service_that_does_not_exist_is_refused(self) -> None:
        self.assertEqual(self.ask({"kind": "throwaway", "action": "start", "name": "nginx"})["code"], 126)

    def saved_memory(self) -> str:
        path = os.path.join(self.project, ".journal", "plugin-data", "code-commandments", "detector-memories.json")
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "w") as written:
            json.dump({"Build": "build-1", "Rules": ["First", "Second"],
                       "Own": [{"Fingerprint": 18446744073709551615, "Used": 1000, "Judged": [1], "Found": {"0": [4]}}],
                       "Across": [{"Fingerprint": 2, "Used": 3000, "Judged": [2]}]}, written)
        return path

    def memory(self) -> dict:
        return json.loads(self.ask({"kind": "memory-file", "action": "read"})["out"])

    def test_the_memory_file_is_read_in_the_shape_the_service_writes(self) -> None:
        self.saved_memory()
        reading = self.memory()
        self.assertEqual((reading["entries"], reading["build"], reading["oldest"], reading["newest"]), (2, "build-1", 1000, 3000))

    def test_ageing_the_memory_file_moves_every_used_and_nothing_else(self) -> None:
        path = self.saved_memory()
        self.assertEqual(self.ask({"kind": "memory-file", "action": "age", "many": 28800})["code"], 0)
        with open(path) as read:
            saved = json.load(read)
        self.assertEqual(saved, {"Build": "build-1", "Rules": ["First", "Second"],
                                 "Own": [{"Fingerprint": 18446744073709551615, "Used": 1000 - 28800 * 10**9, "Judged": [1], "Found": {"0": [4]}}],
                                 "Across": [{"Fingerprint": 2, "Used": 3000 - 28800 * 10**9, "Judged": [2]}]})

    def test_a_stamped_memory_file_holds_the_new_build(self) -> None:
        self.saved_memory()
        self.ask({"kind": "memory-file", "action": "stamp", "name": "build-2"})
        self.assertEqual(self.memory()["build"], "build-2")

    def test_a_memory_file_replaced_with_garbage_reads_as_unreadable(self) -> None:
        self.saved_memory()
        self.ask({"kind": "memory-file", "action": "replace", "stdin": "{not json"})
        reading = self.memory()
        self.assertTrue(reading["present"])
        self.assertFalse(reading["readable"])

    def test_a_removed_memory_file_reads_as_not_there(self) -> None:
        self.saved_memory()
        self.ask({"kind": "memory-file", "action": "remove"})
        self.assertFalse(self.memory()["present"])

    def test_an_action_the_memory_file_does_not_have_is_refused(self) -> None:
        self.saved_memory()
        self.assertEqual(self.ask({"kind": "memory-file", "action": "burn"})["code"], 126)

    def test_a_command_runs_in_the_folder_the_story_names(self) -> None:
        worktree = os.path.join(self.project, "worktree")
        os.makedirs(worktree)
        ran = self.ask({"kind": "command", "argv": ["environment", "list"], "in": worktree})
        self.assertEqual(ran["code"], 0)
        self.assertEqual(ran["out"], "journal environment list\n")
        self.assertEqual(ran["err"].strip(), worktree)

    def test_a_hook_for_a_storys_agent_runs_as_that_agent_and_not_as_the_relays_session(self) -> None:
        os.environ["JOURNAL_AGENT_SESSION"] = "the-session-that-started-the-relay"
        self.addCleanup(os.environ.pop, "JOURNAL_AGENT_SESSION", None)
        hook = os.path.join(self.project, ".journal", "bin", "journal-hook")
        with open(hook, "w") as program:
            program.write("#!/bin/sh\nenv | grep -E '^(AGENT_JOURNAL_ACTIVE|JOURNAL_)' | sort\n")
        said = self.ask({"kind": "hook", "stdin": "{}", "actor": "41", "role": "worker"})["out"].splitlines()
        self.assertEqual(said, ["AGENT_JOURNAL_ACTIVE=1", "JOURNAL_ACTOR=worker:41", "JOURNAL_ENV=story-engine", "JOURNAL_ROLE=worker"])

    def test_a_line_a_hook_names_is_read_as_its_agent_and_handed_back(self) -> None:
        self.scratch()
        bin = os.path.join(self.project, ".journal", "bin")
        with open(os.path.join(bin, "journal-hook"), "w") as program:
            program.write("#!/bin/sh\necho '{\"additionalContext\":\"agent-line:7\"}'\n")
        with open(os.path.join(bin, "journal"), "w") as program:
            program.write("#!/bin/sh\necho \"text: the sin for $JOURNAL_ACTOR, asked as $*\"\n")
        said = self.ask({"kind": "hook", "stdin": "{}", "actor": "41", "role": "worker"})["out"]
        self.assertIn("text: the sin for worker:41, asked as agent-line show 7", said)

    def test_every_line_a_hook_names_at_once_is_read(self) -> None:
        self.scratch()
        bin = os.path.join(self.project, ".journal", "bin")
        with open(os.path.join(bin, "journal-hook"), "w") as program:
            program.write("#!/bin/sh\necho '{\"additionalContext\":\"agent-lines 7, 8\"}'\n")
        with open(os.path.join(bin, "journal"), "w") as program:
            program.write("#!/bin/sh\necho \"text: asked as $*\"\n")
        said = self.ask({"kind": "hook", "stdin": "{}", "actor": "41", "role": "worker"})["out"]
        self.assertIn("text: asked as agent-line show 7", said)
        self.assertIn("text: asked as agent-line show 8", said)

    def test_a_hook_reaches_the_journals_hook_entry_with_its_body(self) -> None:
        ran = self.ask({"kind": "hook", "stdin": '{"hook_event_name": "Stop"}'})
        self.assertEqual(ran["out"], f'hook claude {self.project}\n{{"hook_event_name": "Stop"}}')

    def alive(self, pid: int) -> bool:
        try:
            os.kill(pid, 0)
        except ProcessLookupError:
            return False
        return True

    def test_a_fake_agent_is_handed_a_process_of_its_own_in_a_session_of_its_own(self) -> None:
        ran = self.ask({"kind": "pid"})
        pid = int(ran["out"])
        try:
            self.assertEqual(ran["code"], 0)
            self.assertNotEqual(pid, os.getpid())
            self.assertNotEqual(os.getsid(pid), os.getsid(0))
        finally:
            self.ask({"kind": "pid", "action": "end", "pid": pid})

    def test_the_process_handed_out_holds_neither_claude_nor_codex_in_its_command_line(self) -> None:
        pid = int(self.ask({"kind": "pid"})["out"])
        try:
            command = self.command_line_of(pid)
            self.assertIn("sleep", command)
            self.assertNotIn("claude", command)
            self.assertNotIn("codex", command)
        finally:
            self.ask({"kind": "pid", "action": "end", "pid": pid})

    def command_line_of(self, pid: int) -> str:
        proc = f"/proc/{pid}/cmdline"
        if os.path.exists(proc):
            with open(proc, "rb") as read:
                return read.read().replace(b"\0", b" ").decode()
        return subprocess.run(["ps", "-o", "command=", "-p", str(pid)], capture_output=True, text=True).stdout

    def test_a_process_is_ended_when_its_agent_ends(self) -> None:
        pid = int(self.ask({"kind": "pid"})["out"])
        self.assertTrue(self.alive(pid))
        self.assertEqual(self.ask({"kind": "pid", "action": "end", "pid": pid})["code"], 0)
        self.assertFalse(self.alive(pid))

    def test_every_process_still_handed_out_is_ended_when_the_relay_stops(self) -> None:
        pids = [int(self.ask({"kind": "pid"})["out"]) for _ in range(3)]
        self.relay.shutdown()
        self.relay.server_close()
        self.assertEqual([self.alive(pid) for pid in pids], [False, False, False])

    def test_a_process_the_relay_did_not_hand_out_is_never_ended(self) -> None:
        other = subprocess.Popen(["sleep", "60"], start_new_session=True)
        self.addCleanup(other.wait)
        self.addCleanup(other.terminate)
        self.ask({"kind": "pid", "action": "end", "pid": other.pid})
        self.assertTrue(self.alive(other.pid))

    def test_a_noun_outside_the_list_is_refused_and_never_run(self) -> None:
        ran = self.ask({"kind": "command", "argv": ["rule", "make", "anything"]})
        self.assertEqual(ran["code"], 126)
        self.assertIn("is not one of the journal nouns", ran["err"])
        self.assertEqual(ran["out"], "")

    def test_a_main_agent_is_never_started_for_a_story(self) -> None:
        ran = self.ask({"kind": "command", "argv": ["agent", "start", "story-ada-1", "claude", "main", "--environment", "story-engine"]})
        self.assertEqual(ran["code"], 126)
        self.assertIn("never starts a main agent", ran["err"])
        self.assertEqual(ran["out"], "")

    def test_a_worker_agent_may_still_be_started(self) -> None:
        ran = self.ask({"kind": "command", "argv": ["agent", "start", "story-grace-1", "claude", "worker", "--environment", "story-engine"]})
        self.assertEqual(ran["code"], 0)

    def test_a_folder_outside_the_project_is_refused(self) -> None:
        ran = self.ask({"kind": "command", "argv": ["environment", "list"], "in": "/"})
        self.assertEqual(ran["code"], 126)
        self.assertIn("outside the project", ran["err"])

    def scratch(self, *parts: str) -> str:
        checkout = os.path.join(self.project, ".journal", "worktrees", "story-engine")
        os.makedirs(checkout, exist_ok=True)
        return os.path.join(checkout, *parts)

    def test_a_link_stays_inside_the_scratch_folder(self) -> None:
        folder = self.scratch("bridge", "mypy", "story_scratch_link")
        link = os.path.join(folder, "start/other")

        self.assertEqual(self.ask({"kind": "link", "path": link, "stdin": ".."})["code"], 0)
        self.assertEqual(os.readlink(link), "..")
        self.assertNotEqual(self.ask({"kind": "link", "path": os.path.join(folder, "out"), "stdin": "../../.."})["code"], 0)
        self.assertFalse(os.path.lexists(os.path.join(folder, "out")))

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

    def test_a_file_inside_a_scratch_folder_of_a_story_worktree_is_written(self) -> None:
        path = os.path.join(self.project, ".journal", "worktrees", "story-engine-grace", "bridge", "mypy", "story_scratch_a", "order.py")
        self.assertEqual(self.ask({"kind": "write", "path": path, "stdin": "x = 1\n"})["code"], 0)
        self.assertTrue(os.path.exists(path))

    def test_a_file_in_a_worktree_not_named_for_the_stories_is_refused(self) -> None:
        path = os.path.join(self.project, ".journal", "worktrees", "worker-707", "story_scratch_a", "order.py")
        self.assertEqual(self.ask({"kind": "write", "path": path, "stdin": ""})["code"], 126)
        self.assertFalse(os.path.exists(path))

    def test_a_file_outside_the_test_checkout_is_refused(self) -> None:
        path = os.path.join(self.project, "story_scratch_a", "order.py")
        self.assertEqual(self.ask({"kind": "write", "path": path, "stdin": ""})["code"], 126)
        self.assertFalse(os.path.exists(path))

    def test_a_worktree_not_named_for_the_stories_is_refused(self) -> None:
        ran = self.ask({"kind": "worktree", "action": "cut", "name": "main"})
        self.assertEqual(ran["code"], 126)
        self.assertIn("worktrees are named", ran["err"])

    def test_a_worktree_a_stopped_story_left_behind_is_cut_again(self) -> None:
        checkout = self.scratch()
        for line in (["init", "-q"], ["-c", "user.email=t@t", "-c", "user.name=t", "commit", "-q", "--allow-empty", "-m", "one"]):
            subprocess.run(["git", "-C", checkout, *line], check=True, capture_output=True)
        self.assertEqual(self.ask({"kind": "worktree", "action": "cut", "name": "story-engine-grace"})["code"], 0)
        again = self.ask({"kind": "worktree", "action": "cut", "name": "story-engine-grace"})
        self.assertEqual(again["code"], 0, again["err"])

    def test_a_story_worktree_is_cut_with_no_scan_state_of_a_run_before(self) -> None:
        checkout = self.scratch()
        for line in (["init", "-q"], ["-c", "user.email=t@t", "-c", "user.name=t", "commit", "-q", "--allow-empty", "-m", "one"]):
            subprocess.run(["git", "-C", checkout, *line], check=True, capture_output=True)
        folder = os.path.join(self.project, ".journal", "worktrees", "story-engine-ada")
        state, worklist = self.relay.scan_state_of(folder), self.relay.worklist_of(folder)
        for kept in (state, worklist):
            os.makedirs(kept)
            with open(os.path.join(kept, "lock"), "w"):
                pass
        self.assertEqual(self.ask({"kind": "worktree", "action": "cut", "name": "story-engine-ada"})["code"], 0)
        self.assertFalse(os.path.exists(state))
        self.assertFalse(os.path.exists(worklist))

    def test_a_look_runs_the_journals_file_scan_under_no_agents_session(self) -> None:
        checkout = self.scratch()
        hook = os.path.join(self.project, ".journal", "bin", "journal-hook")
        with open(hook, "w") as program:
            program.write("#!/bin/sh\necho \"$*\"\n")
        ran = self.ask({"kind": "look", "in": checkout})
        self.assertEqual(ran["code"], 0)
        self.assertEqual(ran["out"].strip(), f"--file-scan {self.project}/.journal --session story-engine-no-agent {checkout}")

    def test_a_look_outside_the_story_checkouts_is_refused(self) -> None:
        self.assertEqual(self.ask({"kind": "look", "in": self.project})["code"], 126)

    def test_a_request_that_is_not_json_is_answered_not_dropped(self) -> None:
        with socket.create_connection(self.relay.server_address) as connection:
            connection.sendall(b"not json\n")
            ran = json.loads(connection.makefile().readline())
        self.assertEqual(ran["code"], 126)


if __name__ == "__main__":
    unittest.main()
