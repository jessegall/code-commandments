"""Writes the Python sample stream with bridge/mypy/tree.py, spelling each path as the sample shows it.

Usage: python python.py ([--context] <file> <path-in-stream>)...
Run it with the mypy bridge's interpreter. A file after --context informs the types and is written with
context: true, never judged. From the repository root:

    python contract/samples/emit/python.py \
        tests/Fixtures/python/shop/basket_totals.py /fixtures/python/shop/basket_totals.py \
        tests/Fixtures/python/shop/cli.py /fixtures/python/shop/cli.py \
        --context tests/Fixtures/python/shop/settings.py /fixtures/python/shop/settings.py \
        tests/Fixtures/python/shop/packing.py /fixtures/python/shop/packing.py \
        tests/Fixtures/python/shop/billing/invoice.py /fixtures/python/shop/billing/invoice.py \
        tests/Fixtures/python/shop/voucher_guards.py /fixtures/python/shop/voucher_guards.py \
        > contract/samples/python.jsonl
"""

from __future__ import annotations

import json
import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), "../../../bridge/mypy"))

from bridge import Session  # noqa: E402
from tree import stream  # noqa: E402


def shown_folders(shown: dict[str, str]) -> dict[str, str]:
    """Each real folder above a shown file, spelled as the sample spells it."""
    folders = {}
    for real, named in shown.items():
        real, named = os.path.dirname(real), os.path.dirname(named)
        while real not in folders and named not in ("", "/"):
            folders[real] = named
            real, named = os.path.dirname(real), os.path.dirname(named)
    return folders


def main(argv: list[str]) -> int:
    shown: dict[str, str] = {}
    write: list[str] = []
    rest = list(argv)
    while rest:
        informs = rest[0] == "--context"
        if informs:
            rest.pop(0)
        path, named = os.path.realpath(rest[0]), rest[1]
        shown[path] = named
        if not informs:
            write.append(path)
        rest = rest[2:]
    folders = shown_folders(shown)
    for line in stream(Session(), list(shown), write, None):
        if "header" in line:
            line["header"]["bridge"] = {"name": "contract/samples/emit/python.py", "version": "1"}
            line["header"]["roots"] = [shown[path] for path in line["header"]["roots"]]
        if "file" in line:
            line["file"]["path"] = shown[line["file"]["path"]]
        if "program" in line:
            line["program"]["packages"] = [folders[folder] for folder in line["program"]["packages"]]
        print(json.dumps(line, ensure_ascii=False, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
