import os
import stat
import subprocess
import sys
import tempfile
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


if __name__ == "__main__":
    unittest.main()
