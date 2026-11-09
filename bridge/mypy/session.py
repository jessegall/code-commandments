"""The types of a Python project as mypy resolves them: one session holding the checked project in memory
between requests, so a later one re-checks only the modules whose files changed, and each expression's type read
by its span. tree.py writes them into the generic tree.
"""

from __future__ import annotations

import hashlib
import os
import tempfile
from dataclasses import dataclass

from mypy import build
from mypy.find_sources import InvalidSourceList, create_source_list
from mypy.modulefinder import BuildSource
from mypy.build import BuildResult, State
from mypy.errors import CompileError
from mypy.nodes import Expression
from mypy.options import Options
from mypy.server.subexpr import get_subexpressions
from mypy.types import AnyType, FunctionLike, Instance, NoneType, ProperType, Type, UnionType, get_proper_type
from mypy.util import decode_python_encoding


def checkout_of(paths: list[str]) -> str:
    """The checkout $paths lie in: the nearest folder holding a `.git`, or the first path's own folder."""
    start = os.path.realpath(paths[0]) if paths else os.getcwd()
    folder = start if os.path.isdir(start) else os.path.dirname(start)
    while os.path.dirname(folder) != folder:
        if os.path.exists(os.path.join(folder, ".git")):
            return folder
        folder = os.path.dirname(folder)
    return start


def project_of(checkout: str) -> str:
    """The project $checkout belongs to: itself, or for a git worktree the checkout it was cut from."""
    marker = os.path.join(checkout, ".git")
    if not os.path.isfile(marker):
        return checkout
    with open(marker, encoding="utf-8") as file:
        written = file.read().strip()
    if not written.startswith("gitdir:"):
        return checkout
    git_folder = os.path.realpath(os.path.join(checkout, written.removeprefix("gitdir:").strip()))
    if os.path.basename(os.path.dirname(git_folder)) != "worktrees":
        return checkout
    return os.path.dirname(os.path.dirname(os.path.dirname(git_folder)))


def cache_folder(paths: list[str]) -> str:
    """Where mypy keeps what it checked for the project of $paths, under the plugin's data and never in a checkout:
    one folder for all its worktrees, because mypy accepts a cached module whose path differs when its contents hash
    the same."""
    base = os.environ.get("JOURNAL_PLUGIN_DATA") or tempfile.gettempdir()
    folder = os.path.join(base, "mypy-cache", hashlib.sha256(project_of(checkout_of(paths)).encode()).hexdigest()[:16])
    os.makedirs(folder, exist_ok=True)
    os.utime(folder)
    return folder


def options(python: str | None, cache: str) -> Options:
    """How mypy reads a consumer's project: every body checked, imports it cannot find left untyped, and what it
    checked kept in $cache so a later request compiles only the files it names."""
    chosen = Options()
    chosen.incremental = True
    chosen.cache_dir = cache
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


def read_afresh(found: list[BuildSource]) -> list[BuildSource]:
    """The sources with their text in hand, which makes mypy compile each whatever its cache holds, so each has
    the tree and the types the writer reads. A file deleted between mypy's listing of the folder and this read, a
    worktree removed under the run, is no part of the project this request asks about and is left out; a module
    with no file of its own is kept as it is."""
    kept: list[BuildSource] = []
    for source in found:
        if not source.path:
            kept.append(source)
            continue
        try:
            with open(source.path, "rb") as file:
                source.text = decode_python_encoding(file.read())
        except (OSError, UnicodeDecodeError):
            continue
        kept.append(source)
    return kept


def built(found: list[BuildSource], chosen: Options) -> BuildResult | None:
    """A build of $found. A module mypy refuses to build at all — one that shadows a standard-library module, like
    a top-level `logging.py` — is left out and so untyped, rather than taking every other module's types down with
    it; none when nothing is left to build."""
    while found:
        try:
            return build.build(found, chosen)
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
    """The files a request names, compiled afresh, with everything they import read from mypy's cache."""

    def checked(self, paths: list[str], python: str | None) -> CheckedProject:
        chosen = options(python, cache_folder(paths))
        found = read_afresh(sources(paths, chosen))
        result = built(found, chosen)
        if result is None:
            return CheckedProject(found, {}, {})
        return CheckedProject(found, result.types, result.graph)


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
