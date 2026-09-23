"""The types of a Python project as mypy resolves them, written as JSON lines for the PHP engine.

`python bridge.py <path>...` types every module under the paths once. `python bridge.py --serve` answers one
JSON request per stdin line with the same lines, holding the checked project in memory between requests so a
later one re-checks only the modules whose files changed. CONTRACT.md beside this file is the output format.
"""

from __future__ import annotations

import json
import os
import sys
from typing import Iterator

from mypy import build
from mypy.find_sources import InvalidSourceList, create_source_list
from mypy.modulefinder import BuildSource
from mypy.build import State
from mypy.nodes import Expression
from mypy.options import Options
from mypy.server.subexpr import get_subexpressions
from mypy.server.update import FineGrainedBuildManager
from mypy.types import AnyType, Instance, NoneType, ProperType, Type, UnionType, get_proper_type

VERSION = 1


def options(python: str | None) -> Options:
    """How mypy reads a consumer's project: every body checked, imports it cannot find left untyped, and the
    whole graph kept in memory so a later request re-checks only what changed."""
    chosen = Options()
    chosen.incremental = True
    chosen.fine_grained_incremental = True
    chosen.cache_dir = os.devnull
    chosen.preserve_asts = True
    chosen.export_types = True
    chosen.check_untyped_defs = True
    chosen.follow_imports = "silent"
    chosen.ignore_missing_imports = True
    if python:
        chosen.python_executable = python
    return chosen


def sources(paths: list[str], chosen: Options) -> list[BuildSource]:
    """The modules under $paths, named as mypy names them."""
    try:
        return create_source_list(paths, chosen)
    except InvalidSourceList:
        return []


def stamp(path: str) -> tuple[float, int]:
    status = os.stat(path)
    return status.st_mtime, status.st_size


class Session:
    """One project held checked in memory: built whole on the first request, then updated by the modules
    whose files changed since the last."""

    def __init__(self) -> None:
        self.key: tuple[tuple[str, ...], str | None] | None = None
        self.manager: FineGrainedBuildManager | None = None
        self.stamps: dict[str, tuple[float, int]] = {}

    def checked(self, paths: list[str], python: str | None) -> tuple[list[BuildSource], dict[Expression, Type], dict[str, State]]:
        chosen = options(python)
        found = sources(paths, chosen)
        stamps = {s.path: stamp(s.path) for s in found if s.path}
        key = (tuple(sorted(paths)), python)
        if self.manager is None or key != self.key or stamps.keys() != self.stamps.keys():
            self.manager = FineGrainedBuildManager(build.build(found, chosen)) if found else None
        else:
            changed = [(s.module, s.path) for s in found if s.path and stamps[s.path] != self.stamps[s.path]]
            if changed:
                self.manager.update(changed, [])
        self.key, self.stamps = key, stamps
        if self.manager is None:
            return found, {}, {}
        return found, self.manager.manager.all_types, self.manager.graph


def line_starts(text: bytes) -> list[int]:
    """The byte offset each line of $text begins at."""
    return [0, *(at + 1 for at, byte in enumerate(text) if byte == 0x0A)]


def described(typ: Type) -> dict[str, object] | None:
    """What the contract says of one resolved type — none for `Any`, which resolved nothing."""
    proper = get_proper_type(typ)
    if isinstance(proper, AnyType):
        return None
    present: ProperType = proper
    nullable = False
    if isinstance(proper, UnionType):
        others = [get_proper_type(item) for item in proper.items if not isinstance(get_proper_type(item), NoneType)]
        nullable = len(others) < len(proper.items)
        present = others[0] if len(others) == 1 else proper
    fact: dict[str, object] = {"type": str(typ), "nullable": nullable}
    if isinstance(present, Instance):
        fact["class"] = present.type.fullname
    return fact


def states(graph: dict[str, State], wanted: set[str]) -> dict[str, State]:
    """The checked modules in $wanted, by their resolved path."""
    return {os.path.realpath(state.path): state for state in graph.values() if state.path and os.path.realpath(state.path) in wanted}


def typed(session: Session, paths: list[str], write: list[str], python: str | None) -> Iterator[dict[str, object]]:
    """The contract's lines for the modules under $paths: the version, one line per file, the resolution."""
    yield {"version": VERSION}
    found, types, graph = session.checked(paths, python)
    wanted = {os.path.realpath(p) for p in write} if write else {os.path.realpath(s.path) for s in found if s.path}
    seen = resolved = 0
    for path, state in states(graph, wanted).items():
        written = [e for e in get_subexpressions(state.tree) if e in types] if state.tree is not None else []
        starts = line_starts(open(path, "rb").read())
        spans: dict[tuple[int, int], dict[str, object]] = {}
        for expression in written:
            seen += 1
            if expression.line < 1 or expression.end_line is None or expression.end_column is None or expression.end_line > len(starts):
                continue
            span = (starts[expression.line - 1] + expression.column, starts[expression.end_line - 1] + expression.end_column)
            fact = described(types[expression])
            if fact is not None and span not in spans:
                spans[span] = fact
        resolved += len(spans)
        yield {"path": path, "types": [{"start": start, "end": end, **fact} for (start, end), fact in sorted(spans.items())]}
    yield {"resolution": {"expressions": seen, "typed": resolved}}


def emit(lines: Iterator[dict[str, object]]) -> None:
    for line in lines:
        sys.stdout.write(json.dumps(line, separators=(",", ":")) + "\n")
    sys.stdout.flush()


def main(argv: list[str]) -> int:
    session = Session()
    if "--serve" in argv:
        for request in sys.stdin:
            asked = json.loads(request)
            emit(typed(session, asked.get("paths", []), asked.get("write", []), asked.get("python")))
        return 0
    emit(typed(session, [arg for arg in argv if not arg.startswith("--")], [], None))
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
