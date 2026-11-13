import json
import os
import stat
import subprocess
import sys
import tempfile
import time
import unittest
from pathlib import Path

PLUGIN = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(PLUGIN))

import hook  # noqa: E402


def stand_in(folder: Path, script: str) -> Path:
    binary = folder / "commandments-release"
    binary.write_text("#!/bin/sh\n" + script + "\n")
    binary.chmod(binary.stat().st_mode | stat.S_IEXEC)
    return binary


class TheHookHandsEveryEventToTheBinary(unittest.TestCase):
    def test_the_binarys_answer_and_exit_code_are_the_hooks(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            binary = stand_in(Path(folder), 'read line; [ "$1" = plugin-hook ] && echo "{\\"v\\":1,\\"tell\\":\\"$line\\"}"; exit 3')
            answer, code = hook.answered(b'{"event":"idle"}\n', binary)

        self.assertEqual(answer, b'{"v":1,"tell":"{"event":"idle"}"}\n')
        self.assertEqual(code, 3)

    def test_the_program_runs_the_script_under_the_python_the_journal_names(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            named = Path(folder) / "python"
            named.write_text("#!/bin/sh\necho \"ran $1\"\n")
            named.chmod(named.stat().st_mode | stat.S_IEXEC)
            ran = subprocess.run([str(PLUGIN / "hook")], input=b"", stdout=subprocess.PIPE, env={**os.environ, "JOURNAL_PYTHON": str(named)})

        self.assertEqual(ran.stdout.decode().strip(), f"ran {PLUGIN / 'hook.py'}")



def edited(**settings: object) -> dict:
    return {"v": 1, "event": "file_edited", "agent": 7, "role": "main", "settings": settings, "data": {"path": "order.py"}}


class TheHookJudgesLaterOnlyWhatTheSettingsAsk(unittest.TestCase):
    def test_live_judging_off_judges_nothing(self) -> None:
        for event in ("command_starting", "tool_starting", "file_edited", "idle", "ticked"):
            self.assertFalse(hook.judged_later({**edited(judge_edits=False), "event": event}), event)

    def test_live_judging_on_judges_edits_commands_ticks_and_stops(self) -> None:
        for event in ("command_starting", "file_edited", "idle", "ticked"):
            self.assertTrue(hook.judged_later({**edited(judge_edits=True), "event": event}), event)
        self.assertFalse(hook.judged_later({**edited(judge_edits=True), "event": "session_started"}))

    def test_a_workers_edits_need_worker_judging_too(self) -> None:
        for chosen in ({"judge_edits": True, "judge_workers": False, "judge_worker_edits": True},
                       {"judge_edits": True, "judge_workers": True, "judge_worker_edits": False}):
            self.assertFalse(hook.judged_later({**edited(**chosen), "role": "worker"}), chosen)
        self.assertTrue(hook.judged_later({**edited(judge_edits="true", judge_workers="true", judge_worker_edits=True), "role": "worker"}))


class OneAgentsEditsGatherInOneWindow(unittest.TestCase):
    def test_the_first_edit_opens_it_and_later_ones_only_join(self) -> None:
        with tempfile.TemporaryDirectory() as data:
            window = hook.EditWindow(Path(data), 7)
            self.assertTrue(window.gather(b'{"n":1}'))
            self.assertFalse(window.gather(b'{"n":2}'), "a second edit would start a second judging run")
            self.assertTrue(hook.EditWindow(Path(data), 8).gather(b'{"n":3}'), "another agent's edit joined this agent's window")
            self.assertEqual(window.path(".edits").read_bytes(), b'{"n":1}\n{"n":2}\n')

    def test_a_window_whose_run_died_opens_again(self) -> None:
        with tempfile.TemporaryDirectory() as data:
            window = hook.EditWindow(Path(data), 7)
            window.gather(b'{"n":1}')
            long_ago = time.time() - 2 * hook.WINDOW_LEFT_OPEN_AFTER
            os.utime(window.path(".open"), (long_ago, long_ago))
            self.assertTrue(window.gather(b'{"n":2}'))


class AnAgentHasAtMostOneLiveEditJudgingWaiting(unittest.TestCase):
    def test_a_window_whose_run_still_waits_takes_every_edit_however_long_it_waits(self) -> None:
        with tempfile.TemporaryDirectory() as data:
            window = hook.EditWindow(Path(data), 7)
            self.assertTrue(window.gather(b'{"n":1}'))
            event = Path(data) / "event-1.json"
            event.write_text("{}")
            window.names(event)
            long_ago = time.time() - 2 * hook.WINDOW_LEFT_OPEN_AFTER
            os.utime(window.path(".open"), (long_ago, long_ago))

            self.assertFalse(window.gather(b'{"n":2}'), "a second judging was queued beside one still waiting")

            event.unlink()
            self.assertTrue(window.gather(b'{"n":3}'), "a window whose run started and died was never opened again")

    def test_a_run_that_never_started_frees_the_window_after_an_hour(self) -> None:
        with tempfile.TemporaryDirectory() as data:
            window = hook.EditWindow(Path(data), 7)
            window.gather(b'{"n":1}')
            event = Path(data) / "event-1.json"
            event.write_text("{}")
            window.names(event)
            long_ago = time.time() - 2 * hook.QUEUED_AT_MOST
            os.utime(event, (long_ago, long_ago))

            self.assertTrue(window.gather(b'{"n":2}'))

    def test_a_window_the_run_already_closed_is_not_opened_again_by_naming_it(self) -> None:
        with tempfile.TemporaryDirectory() as data:
            window = hook.EditWindow(Path(data), 7)
            window.gather(b'{"n":1}')
            window.path(".open").unlink()
            window.names(Path(data) / "event-1.json")

            self.assertFalse(window.path(".open").exists())


class AJudgingIsQueuedThroughTheJournal(unittest.TestCase):
    def test_the_line_runs_the_binary_on_the_kept_event_in_the_agents_checkout(self) -> None:
        line = hook.judging_line("/bin/journal", Path("/data/events/event-1.json"), 7, Path("/plugin/bin/commandments-release"))
        self.assertEqual(line, ["/bin/journal", "plugin", "run", "--kind", "judge", "--agent", "7", "--",
                                "/plugin/bin/commandments-release", "plugin-hook", "--judge-event=/data/events/event-1.json"])
        self.assertNotIn("--agent", hook.judging_line("/bin/journal", Path("/e.json"), 0))

    def test_an_edit_keeps_its_event_and_starts_one_run(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            data, asked = Path(folder) / "data", Path(folder) / "asked"
            journal = stand_in(Path(folder), f'echo "$@" >> {asked}')
            line = json.dumps(edited(judge_edits=True)).encode()
            with patched_environment(JOURNAL_PLUGIN_DATA=str(data), JOURNAL_BIN=str(journal)):
                hook.judge_later(line)
                hook.judge_later(line)
            deadline = time.time() + 5
            while not asked.exists() and time.time() < deadline:
                time.sleep(0.05)
            kept = list((data / "events").glob("event-*.json"))

            self.assertEqual(len(kept), 1, "the second edit inside the window kept an event of its own")
            self.assertEqual(kept[0].read_bytes(), line)
            self.assertIn(f"--judge-event={kept[0]}", asked.read_text())

    def test_an_edit_whose_judging_cannot_start_leaves_no_window_open(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            data = Path(folder)
            with patched_environment(JOURNAL_PLUGIN_DATA=str(data), JOURNAL_BIN="", PATH="/nonexistent", JOURNAL_ROOT=folder):
                hook.judge_later(json.dumps(edited(judge_edits=True)).encode())

            self.assertFalse(hook.EditWindow(data, 7).path(".open").exists())
            self.assertEqual(list((data / "events").glob("event-*.json")) if (data / "events").exists() else [], [])

    def test_live_judging_off_starts_nothing(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            with patched_environment(JOURNAL_PLUGIN_DATA=folder):
                hook.judge_later(json.dumps(edited(judge_edits=False)).encode())
            self.assertEqual(list(Path(folder).iterdir()), [])


class patched_environment:
    def __init__(self, **named: str) -> None:
        self.named, self.kept = named, {}

    def __enter__(self) -> None:
        for name, value in self.named.items():
            self.kept[name] = os.environ.get(name)
            os.environ[name] = value

    def __exit__(self, *_: object) -> None:
        for name, value in self.kept.items():
            if value is None:
                os.environ.pop(name, None)
            else:
                os.environ[name] = value


if __name__ == "__main__":
    unittest.main()
