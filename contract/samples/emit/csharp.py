"""Writes the C# sample stream with the Roslyn bridge's --tree mode, spelling each path as the sample shows it.

Usage: python3 csharp.py <project-folder> (<file> <path-in-stream>)...
The whole folder is compiled, so types and targets resolve across it; only the files named are written. The bridge
runs in a memory-capped container of its prebuilt image through bridge/roslyn/roslyn-in-docker.sh, never on the
host. From the repository root:

    python3 contract/samples/emit/csharp.py tests/Fixtures/csharp \
        tests/Fixtures/csharp/Shop/Orders/GiftWrapping.cs /fixtures/csharp/Shop/Orders/GiftWrapping.cs \
        tests/Fixtures/csharp/Shop/Shipping/Tracking.cs /fixtures/csharp/Shop/Shipping/Tracking.cs \
        tests/Fixtures/csharp/Shop/Stock/Audits.cs /fixtures/csharp/Shop/Stock/Audits.cs \
        tests/Fixtures/csharp/Shop/Reports/PageReader.cs /fixtures/csharp/Shop/Reports/PageReader.cs \
        > contract/samples/csharp.jsonl
"""

import json
import os
import subprocess
import sys


def main(argv: list[str]) -> int:
    folder, pairs = argv[0], argv[1:]
    shown = {os.path.realpath(pairs[at]): pairs[at + 1] for at in range(0, len(pairs), 2)}
    request = json.dumps({"paths": [os.path.realpath(folder)], "write": list(shown)})
    script = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "..", "..", "bridge", "roslyn", "roslyn-in-docker.sh")
    answer = subprocess.run(["bash", script, os.path.realpath(folder) + ":ro", "--", "--tree", "--serve"], input=request + "\n", capture_output=True, text=True, check=True)
    files = []
    for line in answer.stdout.splitlines():
        entry = json.loads(line)
        if "header" in entry:
            entry["header"]["bridge"] = {"name": "contract/samples/emit/csharp.py", "version": "1"}
            entry["header"]["roots"] = list(shown.values())
            header = entry
        elif "file" in entry and entry["file"]["path"] in shown:
            entry["file"]["path"] = shown[entry["file"]["path"]]
            files.append(entry)
        elif "program" in entry:
            program = entry
        elif "trailer" in entry:
            entry["trailer"]["files"] = len(files)
            trailer = entry
    for entry in [header, *sorted(files, key=lambda file: list(shown.values()).index(file["file"]["path"])), program, trailer]:
        print(json.dumps(entry, ensure_ascii=False, separators=(",", ":")))

    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
