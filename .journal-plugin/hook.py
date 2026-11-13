"""The plugin's answer to every event the agent journal hands it: one JSON line in, one out. For now it hands the
line to the release binary the manifest pins and prints that binary's answer; the event handling moves here from
the binary step by step, so a change to it ships without a build."""

import subprocess
import sys
from pathlib import Path

PLUGIN = Path(__file__).resolve().parent.parent
BINARY = PLUGIN / "bin" / "commandments-release"


def answered(line: bytes, binary: Path = BINARY) -> tuple[bytes, int]:
    run = subprocess.run([str(binary), "plugin-hook"], input=line, stdout=subprocess.PIPE)
    return run.stdout, run.returncode


def main() -> int:
    answer, code = answered(sys.stdin.buffer.read())
    sys.stdout.buffer.write(answer)
    sys.stdout.buffer.flush()
    return code


if __name__ == "__main__":
    sys.exit(main())
