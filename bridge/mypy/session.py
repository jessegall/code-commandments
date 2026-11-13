"""The types of a Python project as mypy resolves them: one session holding the checked project in memory
between requests, so a later one re-checks only the modules whose files changed, and each expression's type read
by its span. tree.py writes them into the generic tree.
"""

from __future__ import annotations

import functools
import gc
import hashlib
import os
import shutil
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
from mypy.typestate import reset_global_state
from mypy.util import decode_python_encoding


def folder_of(paths: list[str]) -> str:
    """The folder the first of $paths lies in: the checkout of a run that was not told one."""
    start = os.path.realpath(paths[0]) if paths else os.getcwd()
    return start if os.path.isdir(start) else os.path.dirname(start)


def cache_folder(checkout: str, project: str | None, base: str | None) -> str:
    """Where mypy keeps what it checked for $checkout, under $base (else the system's temporary folder). mypy keys what
    it keeps by module name, so two checkouts holding different versions of one module would overwrite each other's and
    recheck it on every run: each checkout keeps its own, a worktree's starting from a copy of what its $project's
    checkout holds. Git's answer for both is the caller's: the bridge is told them."""
    root = base or tempfile.gettempdir()
    folder = os.path.join(root, folder_name(checkout))
    if not os.path.isdir(folder) and project:
        copied_from(os.path.join(root, folder_name(project)), folder)
    os.makedirs(folder, exist_ok=True)
    os.utime(folder)
    return folder


def folder_name(checkout: str) -> str:
    return hashlib.sha256(checkout.encode()).hexdigest()[:16]


def copied_from(project: str, folder: str) -> None:
    """$folder as a copy of $project's, made aside and moved in whole, so a run beside it never sees half of it."""
    if project == folder or not os.path.isdir(project):
        return
    aside = tempfile.mkdtemp(dir=os.path.dirname(folder))
    try:
        shutil.copytree(project, aside, dirs_exist_ok=True)
        os.rename(aside, folder)
    except OSError:
        shutil.rmtree(aside, ignore_errors=True)


def base_folders(found: list[str]) -> list[str]:
    """The folders the project's modules are imported from: above each file's outermost package."""
    bases: set[str] = set()
    for folder in {os.path.dirname(path) for path in found}:
        while os.path.isfile(os.path.join(folder, "__init__.py")):
            folder = os.path.dirname(folder)
        bases.add(folder)
    return sorted(bases)


# real_path is os.path.realpath held once a process: a read resolves the same module paths for every file that imports
# them, and each resolution asks the file system about every folder above the file.
real_path = functools.lru_cache(maxsize=None)(os.path.realpath)


def options(cache: str) -> Options:
    """How mypy reads a consumer's project: every body checked, imports it cannot find left untyped, and what it
    checked kept in $cache, one file per module so several processes reading side by side share it, so a later request compiles only
    the files it names."""
    chosen = Options()
    chosen.incremental = True
    chosen.cache_dir = cache
    chosen.sqlite_cache = False
    chosen.preserve_asts = True
    chosen.export_types = True
    chosen.check_untyped_defs = True
    chosen.follow_imports = "silent"
    chosen.ignore_missing_imports = True
    return chosen


def sources(paths: list[str], chosen: Options) -> list[BuildSource]:
    """The modules under $paths, named as mypy names them."""
    try:
        return create_source_list(paths, chosen)
    except InvalidSourceList:
        return []


def read_afresh(found: list[BuildSource], only: set[str] | None = None) -> list[BuildSource]:
    """The sources with their text in hand, which makes mypy compile each whatever its cache holds, so each has
    the tree and the types the writer reads. A file deleted between mypy's listing of the folder and this read, a
    worktree removed under the run, is no part of the project this request asks about and is left out; a module
    with no file of its own is kept as it is."""
    kept: list[BuildSource] = []
    for source in found:
        if not source.path or (only is not None and real_path(source.path) not in only):
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
    """The files a request names, compiled afresh, with everything they import read from mypy's cache, which is
    kept under $base for the $checkout the files lie in, cut from $project."""

    def __init__(self, base: str | None = None, checkout: str | None = None, project: str | None = None) -> None:
        self.base, self.checkout, self.project = base, checkout, project

    def checkout_of(self, paths: list[str]) -> str:
        """The checkout the run was told, else the folder $paths lie in."""
        return self.checkout or folder_of(paths)

    def compiled(self, asked: list[str], found: list[str]) -> CheckedProject:
        """The modules $asked compiled afresh, everything they import read from mypy's cache or checked once, the
        project's other modules ($found) there to be imported."""
        chosen = options(cache_folder(self.checkout_of(found or asked), self.project, self.base))
        chosen.mypy_path = base_folders(found)
        sources_asked = read_afresh(sources(asked, chosen))
        result = built(sources_asked, chosen)
        if result is None:
            return CheckedProject(sources_asked, {}, {})
        return CheckedProject(sources_asked, result.types, result.graph)

    def forget(self) -> None:
        """Lets go of what the last request built. mypy is written for one build to a process: it keeps its subtype
        and protocol caches for the life of the process, each holding the classes of the build that filled it, and it
        sets the collector to all but never run a full pass, so a build's modules, which all refer to one another,
        are never freed. A bridge that serves answers one build per request, and each one stayed in memory whole."""
        reset_global_state()
        gc.collect()


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
    return {real_path(state.path): state for state in graph.values() if state.path and real_path(state.path) in wanted}


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
