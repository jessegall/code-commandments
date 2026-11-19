import importlib.util
import io
import json
import os
import sys
import tempfile
import unittest
from pathlib import Path
from unittest import mock

PLUGIN = Path(__file__).resolve().parent.parent
spec = importlib.util.spec_from_file_location("v4_hook", PLUGIN / "v4" / "hook.py")
v4_hook = importlib.util.module_from_spec(spec)
spec.loader.exec_module(v4_hook)


class AJournalBeforeVersion5JudgesNoEditUnlessLiveJudgingIsOn(unittest.TestCase):
    def answer_to(self, event: dict) -> dict:
        line = json.dumps(event).encode()
        with mock.patch.dict(os.environ, {"JOURNAL_SETTINGS": "", "JOURNAL_PLUGIN_DATA": ""}), \
                mock.patch.object(v4_hook, "answered", side_effect=AssertionError("the binary judged the edit")), \
                mock.patch.object(sys, "stdin", io.TextIOWrapper(io.BytesIO(line))), \
                mock.patch.object(sys, "stdout", io.StringIO()) as said:
            self.assertEqual(v4_hook.main(), 0)
        return json.loads(said.getvalue())

    def test_an_edit_with_no_settings_given_is_answered_with_nothing(self) -> None:
        self.assertEqual(self.answer_to({"event": "file_edited", "agent": 1}), {"v": 1})

    def test_an_edit_with_live_judging_off_is_answered_with_nothing(self) -> None:
        self.assertEqual(self.answer_to({"event": "file_edited", "agent": 1, "settings": {"judge_edits": False, "judge_record": True}}), {"v": 1})

    def test_an_edit_with_live_judging_on_reaches_the_binary(self) -> None:
        with self.assertRaisesRegex(AssertionError, "the binary judged the edit"):
            self.answer_to({"event": "file_edited", "agent": 1, "settings": {"judge_edits": True}})


class AJudgingIsStartedWithoutAJournalCommandVersion4Lacks(unittest.TestCase):
    def test_a_kept_edit_is_judged_by_the_scripts_own_run(self) -> None:
        with tempfile.TemporaryDirectory() as folder, mock.patch.dict(os.environ, {"JOURNAL_PLUGIN_DATA": folder}), \
                mock.patch.object(v4_hook.subprocess, "Popen") as started:
            event = v4_hook.queued(b'{"event": "file_edited"}')

        line = started.call_args.args[0]
        self.assertEqual(line, [str(PLUGIN / "v4" / "hook"), f"--judge-event={event}"])
        self.assertNotIn("journal", Path(line[0]).name)


if __name__ == "__main__":
    unittest.main()
