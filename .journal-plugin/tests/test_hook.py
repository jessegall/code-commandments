import io
import json
import os
import stat
import subprocess
import sys
import tempfile
import time
import unittest
from unittest import mock
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
    def test_the_binarys_answer_is_said_in_the_journals_words_with_its_exit_code(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            binary = stand_in(Path(folder), 'read line; [ "$1" = plugin-hook ] && echo "{\\"whisper\\":\\"heard $line\\"}"; exit 3')
            answer, code = hook.answered(b'idle\n', binary)

        self.assertEqual(json.loads(answer), {"v": 1, "answer": "tell", "tell": "heard idle"})
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

    def test_the_role_is_read_as_the_binary_reads_it(self) -> None:
        for event in ({"role": " Worker "}, {"data": {"role": "worker"}}, {"role": "", "data": {"role": "HELPER"}}):
            self.assertIn(hook.role_of(event), hook.WORKER_ROLES, event)
        self.assertEqual(hook.role_of({}), "")

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
            self.assertEqual(window.path(".events").read_bytes(), b'{"n":1}\n{"n":2}\n')

    def test_a_window_never_named_by_a_run_stands_only_a_few_seconds(self) -> None:
        with tempfile.TemporaryDirectory() as data:
            window = hook.EditWindow(Path(data), 7)
            window.gather(b'{"n":1}')
            self.assertFalse(window.gather(b'{"n":2}'), "a window just opened let a second run be queued")
            long_ago = time.time() - 2 * hook.UNNAMED_WINDOW_STANDS
            os.utime(window.path(".open"), (long_ago, long_ago))
            self.assertTrue(window.gather(b'{"n":3}'))


class AHandInJudgedAfterGoesThroughAtOnce(unittest.TestCase):
    def test_only_judging_after_without_judging_before_lets_it_through_to_be_judged_later(self) -> None:
        handed_in = {"event": "suites.coding.workers.worker_delivering", "agent": 7}
        self.assertTrue(hook.judged_after_the_hand_in({**handed_in, "settings": {"judge_workers": True, "judge_hand_ins_after": True}}))
        for chosen in ({"judge_workers": False, "judge_hand_ins_after": True},
                       {"judge_workers": True, "judge_hand_ins_after": True, "judge_hand_ins": True},
                       {"judge_workers": True}):
            self.assertFalse(hook.judged_after_the_hand_in({**handed_in, "settings": chosen}), chosen)
        self.assertFalse(hook.judged_after_the_hand_in({"event": "file_edited", "settings": {"judge_workers": True, "judge_hand_ins_after": True}}))

    def test_it_is_answered_with_nothing_and_its_judging_is_queued_for_the_worker(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            data, asked = Path(folder) / "data", Path(folder) / "asked"
            journal = stand_in(Path(folder), f'echo "$@" >> {asked}')
            line = json.dumps({"event": "worker_delivering", "agent": 7, "settings": {"judge_workers": True, "judge_hand_ins_after": True}})
            program = subprocess.run([sys.executable, str(PLUGIN / "hook.py")], input=line.encode(), stdout=subprocess.PIPE,
                                     env={**os.environ, "JOURNAL_PLUGIN_DATA": str(data), "JOURNAL_BIN": str(journal)})
            deadline = time.time() + 5
            while not asked.exists() and time.time() < deadline:
                time.sleep(0.05)

            self.assertEqual(json.loads(program.stdout), {"v": 1})
            kept = list((data / "events").glob("event-*.json"))
            self.assertEqual(len(kept), 1)
            self.assertIn("--agent 7", asked.read_text())
            self.assertIn(f"--judge-agent={kept[0]}", asked.read_text())
            self.assertEqual(hook.EditWindow(data, 7).pending(), [line.encode()])


class AnAgentsWindowStandsExactlyAsLongAsItsRunLives(unittest.TestCase):
    def test_a_live_run_holds_the_window_however_long_it_waits_and_a_killed_one_frees_it_at_once(self) -> None:
        with tempfile.TemporaryDirectory() as data:
            window = hook.EditWindow(Path(data), 7)
            self.assertTrue(window.gather(b'{"n":1}'))
            holder = subprocess.Popen(["sleep", "30"])
            try:
                window.names(holder.pid, Path(data) / "event-1.json")
                long_ago = time.time() - 3600
                os.utime(window.path(".open"), (long_ago, long_ago))
                self.assertFalse(window.gather(b'{"n":2}'), "a second judging was queued beside one still waiting")
            finally:
                holder.kill()
                holder.wait()
            self.assertTrue(window.gather(b'{"n":3}'), "a window whose run was killed was never opened again")
            self.assertEqual(window.pending(), [b'{"n":1}', b'{"n":2}', b'{"n":3}'])

    def test_a_window_the_run_already_closed_is_not_opened_again_by_naming_it(self) -> None:
        with tempfile.TemporaryDirectory() as data:
            window = hook.EditWindow(Path(data), 7)
            window.gather(b'{"n":1}')
            window.path(".open").unlink()
            window.names(os.getpid(), Path(data) / "event-1.json")

            self.assertFalse(window.path(".open").exists())


class AJudgingIsQueuedThroughTheJournal(unittest.TestCase):
    def test_the_line_runs_the_binary_on_the_kept_event_in_the_agents_checkout(self) -> None:
        line = hook.judging_line("/bin/journal", Path("/data/events/event-1.json"), 7, Path("/plugin/.journal-plugin/hook"))
        self.assertEqual(line, ["/bin/journal", "plugin", "run", "--kind", "judge", "--agent", "7", "--",
                                "/plugin/.journal-plugin/hook", "--judge-agent=/data/events/event-1.json"])
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

            self.assertEqual(len(kept), 1, "the second edit inside the window started a run of its own")
            self.assertEqual(json.loads(kept[0].read_bytes()), {"agent": 7})
            self.assertIn(f"--judge-agent={kept[0]}", asked.read_text())
            self.assertEqual(hook.EditWindow(data, 7).pending(), [line, line])

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


class TheBinarysAnswerIsShapedForTheJournal(unittest.TestCase):
    def test_each_answer_reads_as_the_journal_reads_it(self) -> None:
        refused, advice = "the shared branch takes no edits", "Code Commandments — a sin"
        for fields, want in (
            ({}, {"v": 1}),
            ({"refuse": refused}, {"v": 1, "answer": "refuse", "reason": refused}),
            ({"refuse": refused, "whisper": advice}, {"v": 1, "answer": "refuse", "reason": f"{refused}\n\n{advice}"}),
            ({"whisper": advice}, {"v": 1, "answer": "tell", "tell": advice}),
            ({"requires": ["commandments-python-absence"], "judged_ms": 9, "read_ms": 4},
             {"v": 1, "require_skills": ["commandments-python-absence"], "judged_ms": 9, "read_ms": 4}),
            ({"judged_ms": 9}, {"v": 1}),
        ):
            self.assertEqual(hook.shaped(fields), want)

    def test_an_answer_an_older_binary_already_shaped_passes_as_it_is(self) -> None:
        older = {"v": 1, "answer": "refuse", "reason": "the shared branch takes no edits", "cards": [{"key": "sins:a.py", "kind": "sin-found"}]}
        self.assertEqual(hook.shaped(older), older)

    def test_a_card_says_which_card_it_is_in_both_words(self) -> None:
        card = hook.shaped({"raises": [{"event": "configured", "key": "settings", "brief": "applied", "detail": "x"}]})["cards"][0]
        self.assertEqual(card, {"type": "configured", "kind": "configured", "key": "settings", "label": "applied", "detail": "x"})

    def test_every_found_and_clean_card_is_a_new_one(self) -> None:
        raised = [{"event": "sin-found", "key": "sins:src/A.py", "brief": "1 sin"}, {"event": "sin-resolved", "key": "sins:src/A.py", "brief": "clean"}]
        keys = [card["key"] for _ in range(3) for card in hook.shaped({"raises": raised})["cards"]]
        self.assertEqual(len(set(keys)), 6, "a card reused an earlier card's key, so it would replace it in place")
        self.assertTrue(all(key.startswith("sins:src/A.py@") for key in keys))


class WhatAJudgingToldReachesTheJournalsQueue(unittest.TestCase):
    def test_a_told_answer_is_moved_into_the_queue_in_the_journals_words(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            data = Path(folder)
            (data / "told").mkdir()
            (data / "told" / "1-1.json").write_text(json.dumps({"whisper": "1 sin", "env": "main", "agent": 7}))
            hook.delivered(data)

            self.assertEqual(list((data / "told").iterdir()), [])
            queued = json.loads((data / "queue" / "1-1.json").read_text())
            self.assertEqual(queued, {"v": 1, "answer": "tell", "tell": "1 sin", "env": "main", "agent": 7})

    def test_the_queued_run_judges_through_the_binary_then_delivers(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            data = Path(folder) / "data"
            binary = stand_in(Path(folder), f'mkdir -p {data}/told; echo "{{\\"whisper\\":\\"$2\\"}}" > {data}/told/1-1.json; exit 0')
            with patched_environment(JOURNAL_PLUGIN_DATA=str(data)):
                code = hook.judged("/data/events/event-1.json", binary)

            self.assertEqual(code, 0)
            self.assertEqual(json.loads((data / "queue" / "1-1.json").read_text())["tell"], "--judge-event=/data/events/event-1.json")


class NothingAfterTheAnswerStopsAnEvent(unittest.TestCase):
    def test_settings_given_as_json_text_are_read_and_never_taken_for_a_path(self) -> None:
        chosen = {"judge_edits": True, **{f"key_{n}": "x" * 50 for n in range(40)}}
        with patched_environment(JOURNAL_SETTINGS=json.dumps(chosen)):
            self.assertEqual(hook.settings_of({}), chosen)

    def test_a_fault_after_the_answer_is_logged_not_raised(self) -> None:
        def broken() -> None:
            raise OSError("the event file was removed under us")

        with tempfile.TemporaryDirectory() as data:
            with patched_environment(JOURNAL_PLUGIN_DATA=data):
                hook.afterwards(broken)
            self.assertIn("the event file was removed under us", (Path(data) / hook.FAULTS).read_text())

    def test_a_told_file_that_is_not_an_object_is_dropped(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            data = Path(folder)
            (data / "told").mkdir()
            (data / "told" / "1-1.json").write_text("[1, 2]")
            hook.delivered(data)
            self.assertEqual(list((data / "told").iterdir()), [])

    def test_a_failure_between_gathering_and_naming_reopens_the_window(self) -> None:
        def failing(agent: int) -> Path:
            raise OSError("no room for the event")

        with tempfile.TemporaryDirectory() as data:
            kept, hook.queued = hook.queued, failing
            try:
                with patched_environment(JOURNAL_PLUGIN_DATA=data):
                    with self.assertRaises(OSError):
                        hook.judge_later(json.dumps(edited(judge_edits=True)).encode())
            finally:
                hook.queued = kept
            self.assertFalse(hook.EditWindow(Path(data), 7).path(".open").exists())


class NoToldAnswerIsLost(unittest.TestCase):
    def test_an_answer_a_killed_delivery_left_taken_is_delivered_later(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            data = Path(folder)
            (data / "told").mkdir()
            stranded = data / "told" / "1-9-1.taking"
            stranded.write_text(json.dumps({"whisper": "1 sin"}))
            long_ago = time.time() - 2 * hook.TAKING_AT_MOST
            os.utime(stranded, (long_ago, long_ago))
            hook.delivered(data)
            self.assertEqual(json.loads((data / "queue" / "1-9-1.json").read_text())["tell"], "1 sin")

    def test_a_judging_whose_binary_cannot_start_removes_its_event_so_the_window_opens_again(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            event = Path(folder) / "event-1.json"
            event.write_text("{}")
            self.assertEqual(hook.judged(str(event), Path(folder) / "no-binary"), 1)
            self.assertFalse(event.exists())


class AHelperIsRefusedTheCLIOnlyWhenTheSettingDeniesIt(unittest.TestCase):
    def test_the_aliases_are_the_ones_the_manifest_declares(self) -> None:
        self.assertEqual(hook.cli_aliases(), {"journal sins", "journal commandments-judge-changes", "journal rules", "journal commandments"})

    def test_every_case_the_binary_refused_the_script_refuses(self) -> None:
        cli = "bin/commandments info redundant-else"
        cases = {
            "the setting is left alone": ("worker", cli, None, True),
            "the setting allows it": ("worker", cli, {"workers_cli": True}, False),
            "the setting denies it": ("worker", cli, {"workers_cli": False}, True),
            "the setting says false as words": ("senior", cli, {"workers_cli": "false"}, True),
            "the main agent is not affected": ("main", cli, {"workers_cli": False}, False),
            "another command is not affected": ("worker", "git status", {"workers_cli": False}, False),
            "judging through the journal": ("worker", "journal sins --changes", {"workers_cli": False}, True),
            "after an environment and a chain": ("worker", "cd x && FOO=1 .journal/plugins/code-commandments/bin/commandments-release judge", {"workers_cli": False}, True),
            "through the journal's alias": ("worker", "journal commandments info x", {"workers_cli": False}, True),
            "a project's own rules": ("worker", "journal rules try x", {"workers_cli": False}, True),
            "only named in an argument": ("worker", "grep commandments README.md", {"workers_cli": False}, False),
            "a reviewer or researcher": ("helper", cli, {"workers_cli": False}, True),
            "through a variable": ("worker", "B=/p/.journal/plugins/code-commandments/bin/commandments-release; T=/tmp/w; (cd $T; $B rule explain a.py) 2>&1 | tail -15", {"workers_cli": False}, True),
            "behind env and nice": ("helper", "env -C /p COMMANDMENTS_TIMING=1 nice -n 15 /p/bin/commandments-release judge --no-checklist", {"workers_cli": False}, True),
            "behind timeout in a shell": ("worker", "sh -c 'timeout 60 bin/commandments judge src'", {"workers_cli": False}, True),
            "the custom folder named": ("worker", "T=.commandments/custom; ls $T", {"workers_cli": False}, False),
        }
        with patched_environment(JOURNAL_SETTINGS=""):
            for name, (role, command, settings, refused) in cases.items():
                event = {"event": "command_starting", "role": role, "settings": settings or {}, "data": {"text": command}}
                said = hook.cli_refused(event)
                self.assertEqual(bool(said), refused, name)
                if refused:
                    self.assertIn("workers_cli", said, name)


class ACommandAndAStopAreAnsweredWithoutTheBinary(unittest.TestCase):
    def test_each_is_answered_with_nothing_and_the_binary_never_starts(self) -> None:
        with tempfile.TemporaryDirectory() as folder, patched_environment(JOURNAL_PLUGIN_DATA=folder, JOURNAL_SETTINGS=""):
            for event in ("command_starting", "idle"):
                line = json.dumps({"event": event, "agent": 7, "role": "main", "data": {"text": "git status"}}).encode()
                with mock.patch.object(hook, "answered", side_effect=AssertionError("the binary was started")), \
                        mock.patch.object(sys, "stdin", io.TextIOWrapper(io.BytesIO(line))), \
                        mock.patch.object(sys, "stdout", io.StringIO()) as said:
                    self.assertEqual(hook.main(), 0)
                self.assertEqual(json.loads(said.getvalue()), {"v": 1}, event)


class AGuardHoldsItsStepOnAQueuedJudging(unittest.TestCase):
    def test_a_chosen_guard_is_answered_with_a_run_and_keeps_its_event(self) -> None:
        with tempfile.TemporaryDirectory() as folder, patched_environment(JOURNAL_PLUGIN_DATA=folder, JOURNAL_SETTINGS=""):
            for name, key in (("suites.coding.workers.worker_delivering", "judge_hand_ins"),
                              ("suites.coding.workers.merge_request.created", "judge_before_merge")):
                event = {"event": name, "agent": 7, "window_ms": 30000, "settings": {"judge_workers": True, key: True}}
                said = hook.guard_queued(event, json.dumps(event).encode())
                self.assertEqual(said["answer"], "queue", name)
                self.assertEqual(said["run"]["kind"], "judge", name)
                kept = Path(said["run"]["command"][1][len(hook.JUDGE_GUARD):])
                self.assertEqual(json.loads(kept.read_text()), event, name)
                self.assertIsNone(hook.guard_queued({**event, "settings": {"judge_workers": True}}, b"{}"), name)

    def test_the_run_passes_on_nothing_found_and_refuses_with_the_reason(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            for printed, code in (("{}", 0), ('{\\"whisper\\":\\"allowed unjudged\\"}', 0), ('{\\"refuse\\":\\"one sin\\"}', 1)):
                binary = stand_in(Path(folder), f'cat > /dev/null; echo "{printed}"')
                kept = Path(folder) / "event.json"
                kept.write_text("{}")
                self.assertEqual(hook.guard_judged(str(kept), binary), code, printed)
                self.assertFalse(kept.exists())


class ALandedMergeIsJudgedAfter(unittest.TestCase):
    def test_only_with_merge_judging_chosen(self) -> None:
        merged = {"event": "suites.coding.workers.worker_worktree.merge"}
        self.assertTrue(hook.judged_after_the_merge({**merged, "settings": {"judge_workers": True, "judge_merges": True}}))
        self.assertFalse(hook.judged_after_the_merge({**merged, "settings": {"judge_workers": True}}))
        self.assertFalse(hook.judged_after_the_merge({"event": "file_edited", "settings": {"judge_workers": True, "judge_merges": True}}))


class AnAnswerAfterItsEventOnlyTells(unittest.TestCase):
    def test_a_refusal_is_told_and_its_words_said_once(self) -> None:
        self.assertEqual(hook.told_after({"refuse": "a sin", "whisper": "a sin", "agent": 7}), {"whisper": "a sin", "agent": 7})
        self.assertEqual(hook.told_after({"refuse": "a sin", "whisper": "not whole"}), {"whisper": "a sin\n\nnot whole"})
        self.assertEqual(hook.told_after({"whisper": "fine"}), {"whisper": "fine"})


class AGuardNotChosenIsAnsweredWithoutTheBinary(unittest.TestCase):
    def test_each_guard_and_landed_merge_with_its_setting_off_lets_the_step_through(self) -> None:
        with tempfile.TemporaryDirectory() as folder, patched_environment(JOURNAL_PLUGIN_DATA=folder, JOURNAL_SETTINGS=""):
            for event in ("suites.coding.workers.worker_delivering", "suites.coding.workers.merge_request.created",
                          "suites.coding.workers.worker_worktree.merge"):
                line = json.dumps({"event": event, "agent": 7, "settings": {"judge_workers": True}}).encode()
                with mock.patch.object(hook, "answered", side_effect=AssertionError("the binary was started")), \
                        mock.patch.object(sys, "stdin", io.TextIOWrapper(io.BytesIO(line))), \
                        mock.patch.object(sys, "stdout", io.StringIO()) as said:
                    self.assertEqual(hook.main(), 0)
                self.assertEqual(json.loads(said.getvalue()), {"v": 1}, event)


def every_kind(agent: int) -> list[bytes]:
    chosen = {"judge_edits": True, "judge_workers": True, "judge_hand_ins_after": True, "judge_merges": True, "judge_edit_window": 0}
    events = [{"event": "file_edited", "data": {"path": "a.py"}}, {"event": "file_edited", "data": {"path": "b.py"}},
              {"event": "command_starting", "data": {"text": "ls"}}, {"event": "ticked"}, {"event": "idle"},
              {"event": "suites.coding.workers.worker_delivering"}, {"event": "suites.coding.workers.worker_worktree.merge"}]
    return [json.dumps({"v": 1, **event, "agent": agent, "role": "main", "settings": chosen}).encode() for event in events]


def queue_every_kind(agent: int) -> None:
    for line in every_kind(agent):
        if hook.judged_later(json.loads(line)):
            hook.judge_later(line)
        else:
            hook.judged_for_the_agent(line, agent)


class EveryJudgingOfAnAgentWaitsInOneRun(unittest.TestCase):
    def runs(self, asked: Path) -> list[str]:
        deadline = time.time() + 5
        while time.time() < deadline and not asked.exists():
            time.sleep(0.05)
        time.sleep(0.2)
        return asked.read_text().splitlines() if asked.exists() else []

    def test_every_kind_for_one_agent_queues_one_run_holding_them_all(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            data, asked = Path(folder) / "data", Path(folder) / "asked"
            journal = stand_in(Path(folder), f'echo "$@" >> {asked}')
            with patched_environment(JOURNAL_PLUGIN_DATA=str(data), JOURNAL_BIN=str(journal), JOURNAL_SETTINGS=""):
                queue_every_kind(7)
            runs = self.runs(asked)
            self.assertEqual(len(runs), 1, runs)
            self.assertIn("--agent 7", runs[0])
            self.assertEqual(hook.EditWindow(data, 7).pending(), every_kind(7))

    def test_two_agents_each_queue_one_run(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            data, asked = Path(folder) / "data", Path(folder) / "asked"
            journal = stand_in(Path(folder), f'echo "$@" >> {asked}')
            with patched_environment(JOURNAL_PLUGIN_DATA=str(data), JOURNAL_BIN=str(journal), JOURNAL_SETTINGS=""):
                queue_every_kind(7)
                queue_every_kind(8)
            runs = self.runs(asked)
            self.assertEqual(sorted(run.split("--agent ")[1].split()[0] for run in runs), ["7", "8"], runs)

    def test_the_run_judges_the_edits_as_one_batch_and_every_other_event_on_its_own(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            data, calls = Path(folder) / "data", Path(folder) / "calls"
            journal = stand_in(Path(folder), "true")
            binary = Path(folder) / "binary"
            binary.write_text(f'#!/bin/sh\ncat "${{2#--judge-event=}}" >> {calls}\necho "-----" >> {calls}\n')
            binary.chmod(0o755)
            with patched_environment(JOURNAL_PLUGIN_DATA=str(data), JOURNAL_BIN=str(journal), JOURNAL_SETTINGS=""):
                queue_every_kind(7)
                marker = Path(hook.EditWindow(data, 7).path(".open").read_text().split()[1])
                self.assertEqual(hook.judged_agent(str(marker), binary), 0)
            judged = [batch.strip().splitlines() for batch in calls.read_text().split("-----") if batch.strip()]
            kinds = every_kind(7)
            self.assertEqual(judged[0], [line.decode() for line in kinds[:2]], "the edits were not judged as one batch")
            self.assertEqual([batch for batch in judged[1:]], [[line.decode()] for line in kinds[2:]])
            self.assertFalse(marker.exists())
            self.assertFalse(hook.EditWindow(data, 7).path(".open").exists(), "the run left the agent's window open")
            self.assertEqual(hook.EditWindow(data, 7).pending(), [])

    def test_an_event_after_the_run_took_the_others_queues_exactly_one_new_run(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            data, asked = Path(folder) / "data", Path(folder) / "asked"
            journal = stand_in(Path(folder), f'echo "$@" >> {asked}')
            with patched_environment(JOURNAL_PLUGIN_DATA=str(data), JOURNAL_BIN=str(journal), JOURNAL_SETTINGS=""):
                queue_every_kind(7)
                self.assertEqual(len(hook.EditWindow(data, 7).take()), len(every_kind(7)))
                queue_every_kind(7)
            self.assertEqual(len(self.runs(asked)), 2)

    def test_the_edit_window_is_the_setting_the_last_edit_carried(self) -> None:
        line = lambda event, seconds: json.dumps({"event": event, "settings": {"judge_edit_window": seconds}}).encode()
        self.assertEqual(hook.edit_window_of([line("file_edited", 2), line("file_edited", 3), line("idle", 9)]), 3.0)
        self.assertEqual(hook.edit_window_of([json.dumps({"event": "file_edited", "settings": {}}).encode()]), 5.0)
        self.assertEqual(hook.edit_window_of([line("idle", 9)]), 0.0)


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
