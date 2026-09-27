"""The types of a Python project as mypy resolves them: one session holding the checked project in memory
between requests, so a later one re-checks only the modules whose files changed, and each expression's type read
by its span. tree.py writes them into the generic tree.
"""

from __future__ import annotations

import os
from dataclasses import dataclass

from mypy import build
from mypy.find_sources import InvalidSourceList, create_source_list
from mypy.modulefinder import BuildSource
from mypy.build import State
from mypy.errors import CompileError
from mypy.nodes import Expression
from mypy.options import Options
from mypy.server.subexpr import get_subexpressions
from mypy.server.update import FineGrainedBuildManager
from mypy.types import AnyType, FunctionLike, Instance, NoneType, ProperType, Type, UnionType, get_proper_type

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


def built(found: list[BuildSource], chosen: Options) -> FineGrainedBuildManager | None:
    """A build of $found held for updates. A module mypy refuses to build at all — one that shadows a
    standard-library module, like a top-level `logging.py` — is left out and so untyped, rather than taking
    every other module's types down with it; none when nothing is left to build."""
    while found:
        try:
            return FineGrainedBuildManager(build.build(found, chosen))
        except CompileError as refused:
            blamed = {os.path.realpath(message.split(":", 1)[0]) for message in refused.messages if ": error:" in message}
            kept = [source for source in found if not (source.path and os.path.realpath(source.path) in blamed)]
            if len(kept) == len(found):
                return None
            found = kept
    return None


@dataclass(frozen=True)
class CheckedProject:
    """A project as mypy checked it: its sources, the type of every expression, and each module's state by name."""

    sources: list[BuildSource]
    types: dict[Expression, Type]
    graph: dict[str, State]


class Session:
    """One project held checked in memory: built whole on the first request, then updated by the modules
    whose files changed since the last."""

    def __init__(self) -> None:
        self.key: tuple[tuple[str, ...], str | None] | None = None
        self.manager: FineGrainedBuildManager | None = None
        self.stamps: dict[str, tuple[float, int]] = {}

    def checked(self, paths: list[str], python: str | None) -> CheckedProject:
        chosen = options(python)
        found = sources(paths, chosen)
        stamps = {s.path: stamp(s.path) for s in found if s.path}
        key = (tuple(sorted(paths)), python)
        if self.manager is None or key != self.key or stamps.keys() != self.stamps.keys():
            self.manager = built(found, chosen)
        else:
            changed = [(s.module, s.path) for s in found if s.path and stamps[s.path] != self.stamps[s.path] and s.module in self.manager.graph]
            if changed:
                self.manager.update(changed, [])
        self.key, self.stamps = key, stamps
        if self.manager is None:
            return CheckedProject(found, {}, {})
        return CheckedProject(found, self.manager.manager.all_types, self.manager.graph)


def line_starts(text: bytes) -> list[int]:
    """The byte offset each line of $text begins at."""
    return [0, *(at + 1 for at, byte in enumerate(text) if byte == 0x0A)]


@dataclass(frozen=True)
class ResolvedType:
    """One type mypy resolved: as mypy writes it, whether it admits `None`, the class it is an instance of, and the
    class it builds when called — the last two only where the type has one."""

    text: str
    nullable: bool
    instance_of: str | None = None
    constructs: str | None = None

    def to_json(self) -> dict[str, object]:
        """The type as the contract writes it: named for its class when it has one, opaque otherwise."""
        pairs = (
            ("text", self.text),
            ("kind", "named" if self.instance_of is not None else "opaque"),
            ("name", self.instance_of),
            ("nullable", True if self.nullable else None),
            ("constructs", self.constructs),
            ("origin", "compiler"),
        )
        return {key: value for key, value in pairs if value is not None}


@dataclass(frozen=True)
class TypedModule:
    """How many of a module's expressions mypy typed, and what each typed one resolved to by its `[start, end)` byte
    span; the first expression at a span wins."""

    expressions: int
    by_span: dict[tuple[int, int], ResolvedType]


def described(typ: Type) -> ResolvedType | None:
    """The type mypy resolved — none for `Any`, which resolved nothing."""
    proper = get_proper_type(typ)
    if isinstance(proper, AnyType):
        return None
    present: ProperType = proper
    nullable = False
    if isinstance(proper, UnionType):
        others = [get_proper_type(item) for item in proper.items if not isinstance(get_proper_type(item), NoneType)]
        nullable = len(others) < len(proper.items)
        present = others[0] if len(others) == 1 else proper
    instance_of = present.type.fullname if isinstance(present, Instance) else None
    constructs = present.type_object().fullname if isinstance(present, FunctionLike) and present.is_type_obj() else None
    return ResolvedType(str(typ), nullable, instance_of, constructs)


def states(graph: dict[str, State], wanted: set[str]) -> dict[str, State]:
    """The checked modules in $wanted, by their resolved path."""
    return {os.path.realpath(state.path): state for state in graph.values() if state.path and os.path.realpath(state.path) in wanted}


def spans(path: str, state: State, types: dict[Expression, Type]) -> TypedModule:
    """The module's expressions mypy typed, read by their byte spans."""
    written = [e for e in get_subexpressions(state.tree) if e in types] if state.tree is not None else []
    starts = line_starts(open(path, "rb").read())
    found: dict[tuple[int, int], ResolvedType] = {}
    for expression in written:
        if expression.line < 1 or expression.end_line is None or expression.end_column is None or expression.end_line > len(starts):
            continue
        span = (starts[expression.line - 1] + expression.column, starts[expression.end_line - 1] + expression.end_column)
        resolved = described(types[expression])
        if resolved is not None and span not in found:
            found[span] = resolved
    return TypedModule(len(written), found)
