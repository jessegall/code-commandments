"""A Python project as one generic tree stream (contract/CONTRACT.md), typed by mypy.

`python tree.py <path>...` writes the stream for every module under the paths. `python tree.py --serve` answers one
JSON request per stdin line ({"paths", "write"}) with a full stream, header to trailer: a line for each module
`write` names, every module of `paths` there to import from. Each line names the files its tree read, so the reader
keeps it between runs; this bridge keeps nothing. Types come from session.py's mypy session, joined to nodes by
exact span.
"""

from __future__ import annotations

import ast
import configparser
import fnmatch
import json
import os
import sys
import threading
import time
from dataclasses import dataclass
from typing import Iterator

from mypy.build import State
from mypy.version import __version__ as MYPY_VERSION

from session import Session, TypedModule, real_path, resolver, spans, states
from translate import Translator, numbered, wire

try:
    import tomllib
except ImportError:  # Python 3.10 reads no pyproject.toml settings; the other three files still count
    tomllib = None

def python_files(paths: list[str]) -> list[str]:
    """Every `.py` file named in the paths that is there to be read, resolved and sorted, so the order a request names
    them in never changes what the bridge writes. The bridge walks no
    folder: the Go side lists every file through its one funnel, so a folder named here is refused, and a link, which
    the funnel never hands on, is left out.

    A file named whose file cannot be opened is left out: a file deleted under the run, a worktree removed while it
    was judged. Each used to end the stream in the middle, with every other file of the request lost with it."""
    found: list[str] = []
    for path in paths:
        if os.path.islink(path):
            continue
        if os.path.isdir(path):
            raise SystemExit(f"the Python bridge reads only the files it is handed; {path} is a folder")
        if path.endswith(".py"):
            found.append(os.path.realpath(path))
    return [path for path in sorted(set(found)) if readable(path)]


def readable(path: str) -> bool:
    """Whether the file is there to be read now."""
    try:
        with open(path, "rb"):
            return True
    except OSError:
        return False


def module_name(path: str) -> str:
    """The dotted name Python imports the file by: its path from the top of its outermost package."""
    parts = [] if os.path.basename(path) == "__init__.py" else [os.path.basename(path)[:-3]]
    folder = os.path.dirname(path)
    while os.path.isfile(os.path.join(folder, "__init__.py")):
        parts.insert(0, os.path.basename(folder))
        folder = os.path.dirname(folder)
    return ".".join(parts)


def packages(path: str) -> list[str]:
    """Every folder holding an __init__.py, from the file's own up."""
    found = []
    folder = os.path.dirname(path)
    while os.path.isfile(os.path.join(folder, "__init__.py")):
        found.append(folder)
        folder = os.path.dirname(folder)
    return found


def unparsed(source: bytes) -> dict[str, object]:
    """The root of a file Python's parser refused: a file node whose every part is unknown."""
    return {"id": 0, "kind": "file", "span": [0, len(source), 1], "incomplete": True}


PYTEST_SETTINGS = (("pytest.ini", "pytest"), ("pyproject.toml", "tool.pytest.ini_options"), ("tox.ini", "pytest"), ("setup.cfg", "tool:pytest"))


def pytest_files(folder: str, checkout: str) -> list[str]:
    """The file names pytest collects as tests from $folder, as the nearest pytest settings at or above it name them
    (python_files), up to the checkout; none when no settings name them, since the default names are the contract's."""
    at = folder
    while True:
        for name, section in PYTEST_SETTINGS:
            named = python_files_in(os.path.join(at, name), section)
            if named is not None:
                return named
        if at == checkout or os.path.dirname(at) == at:
            return []
        at = os.path.dirname(at)


def python_files_in(path: str, section: str) -> list[str] | None:
    """The python_files patterns one settings file names in its pytest section, or None when it names none."""
    if not os.path.isfile(path):
        return None
    try:
        if path.endswith(".toml"):
            if tomllib is None:
                return None
            with open(path, "rb") as file:
                table = tomllib.load(file)
            for part in section.split("."):
                table = table.get(part, {}) if isinstance(table, dict) else {}
            value = table.get("python_files") if isinstance(table, dict) else None
            if not value:
                return None
            return [value] if isinstance(value, str) else list(value)
        parser = configparser.ConfigParser()
        parser.read(path)
        value = parser.get(section, "python_files", fallback=None)
        return value.split() if value else None
    except (OSError, ValueError, configparser.Error):
        return None


def is_test(path: str, checkout: str) -> bool:
    """Whether pytest's own settings name the file as a test."""
    return any(fnmatch.fnmatch(os.path.basename(path), pattern) for pattern in pytest_files(os.path.dirname(path), checkout))


def stream(session: Session, paths: list[str], write: list[str]) -> Iterator[dict | str]:
    """The contract's lines for the modules $write names under $paths (every one when it names none): the header, one
    file line each with the files its tree read, the program, the trailer."""
    yield {"header": {"contract": "tree", "version": 5, "language": "python",
                      "bridge": {"name": "mypy-bridge", "version": MYPY_VERSION, "reads": True},
                      "roots": [os.path.realpath(p) for p in paths]}}
    found = python_files(paths)
    judged = {os.path.realpath(p) for p in write}
    asked = [path for path in found if path in judged] if write else found
    project = session.compiled(asked, found)
    named = {real_path(s.path): s.module for s in project.sources if s.path}
    checked = states(project.graph, set(named))
    checkout = session.checkout_of(paths)
    pins = pinned(checkout)
    seen = resolved = unjoined = files = 0
    for path in asked:
        try:
            with open(path, "rb") as file:
                text = file.read()
        except OSError:
            continue
        state = checked.get(path)
        line = line_of(text, named.get(path) or module_name(path), state, project.types, path, is_test(path, checkout))
        seen, resolved, unjoined = seen + line.expressions, resolved + line.typed, unjoined + line.unjoined
        files += 1
        yield placed(line, path, reads_of(state, project.graph, checkout, path) + pins)
    folders: dict[str, None] = {}
    for path in found:
        folders.update(dict.fromkeys(packages(path)))
    yield {"program": {"packageFolders": sorted(folders)}}
    yield {"trailer": {"files": files, "resolution": {"expressions": seen, "typed": resolved, "unjoined": unjoined}}}


PINS = ("pyproject.toml", "requirements.txt", "poetry.lock", "uv.lock", "Pipfile.lock", "setup.cfg", "mypy.ini")


def pinned(checkout: str) -> list[str]:
    """The files of the checkout that pin what is installed and how mypy reads it: a change to one changes every tree."""
    return [path for path in (os.path.join(checkout, name) for name in PINS) if os.path.isfile(path)]


def reads_of(state: State | None, graph: dict[str, State], checkout: str, path: str) -> list[str]:
    """The files of the checkout the module's tree read: each module of it the module depends on, as mypy read it,
    whether or not the request named it."""
    reads: list[str] = []
    for dependency in sorted(set(state.dependencies)) if state is not None else []:
        held = graph.get(dependency)
        read = real_path(held.path) if held is not None and held.path else None
        if read is not None and read != path and read.startswith(checkout + os.sep):
            reads.append(read)
    return reads


@dataclass(frozen=True)
class WrittenLine:
    """A module's file line without its path or reads: the members of its object as JSON text, ready to be put after
    them, and the counts its trailer adds up."""

    members: str
    expressions: int
    typed: int
    unjoined: int


def line_of(text: bytes, module: str, state: State | None, types: dict, path: str, test: bool) -> WrittenLine:
    """A module's file line with its path and context left out, and the counts the trailer adds up for it."""
    typed = spans(path, state, types) if state is not None else TypedModule(0, {})
    ran = state is not None and state.tree is not None
    translator = Translator(text, module, resolver(state if ran else None), typed.by_span, typed.targets or {}, typed.names or {}, typed.obligations or {}, typed.enum_members or {}, typed.module_paths or {}, typed.constants or {}, typed.instance_field_classes)
    try:
        root = translator.file(ast.parse(text))
    except SyntaxError:
        root = None
    line: dict[str, object] = {"language": "python", "errors": 0 if root is not None else 1}
    if test:
        line["test"] = True
    line["module"] = module
    line["resolver"] = {"tool": "mypy", "ran": ran}
    if root is None:
        line["root"], line["comments"] = unparsed(text), []
    else:
        nodes = numbered(root)
        line["root"] = wire(root, translator.starts)
        line["comments"] = translator.comments(root, nodes)
        if translator.table:
            line["types"] = translator.table
    unjoined = sum(1 for span in typed.by_span if span not in translator.joined)
    members = json.dumps(line, ensure_ascii=False, separators=(",", ":"))[1:-1]
    return WrittenLine(members, typed.expressions, len(typed.by_span), unjoined)


def placed(line: WrittenLine, path: str, reads: list[str]) -> str:
    """The file line of the module at $path, with the files its tree read, put together as text."""
    return ('{"file":{"path":' + json.dumps(path, ensure_ascii=False) + ',"reads":' + json.dumps(reads, ensure_ascii=False)
            + "," + line.members + "}}")


def emit(lines: Iterator[dict]) -> None:
    # A source whose bytes are not UTF-8 reaches us as lone surrogates, which UTF-8 cannot carry back out: one
    # such file used to raise mid-stream and cost us every file after it. They are replaced, so that file loses
    # a character of text and every other is still read.
    sys.stdout.reconfigure(errors="replace")
    for line in lines:
        sys.stdout.write((line if isinstance(line, str) else json.dumps(line, ensure_ascii=False, separators=(",", ":"))) + "\n")
    sys.stdout.flush()


def answered(session: Session, request: str) -> None:
    """One request of a serving bridge, answered, and its build let go of."""
    asked = json.loads(request)
    try:
        emit(stream(session, asked.get("paths", []), asked.get("write", [])))
    finally:
        session.forget()


def leaves_with_its_parent() -> None:
    """Ends this bridge once the run that started it is gone. A run killed outright does not always close our input
    (another process may still hold it, or a request is being compiled), and a bridge left like that compiles on for
    no one: it is taken in by the system's first process, which is how its parent being gone is seen."""
    parent = os.getppid()

    def watch() -> None:
        while os.getppid() == parent:
            time.sleep(1)
        os._exit(0)

    threading.Thread(target=watch, daemon=True).start()


def option(argv: list[str], name: str) -> str | None:
    """The value of the option $name, written `name=value`; none when it is not given."""
    for arg in argv:
        if arg.startswith(name) and arg[len(name):]:
            return arg[len(name):]
    return None


def main(argv: list[str]) -> int:
    session = Session(option(argv, "--mypy-cache="), option(argv, "--checkout="), option(argv, "--project="))
    if "--serve" in argv:
        leaves_with_its_parent()
        for request in sys.stdin:
            answered(session, request)
        return 0
    emit(stream(session, [arg for arg in argv if not arg.startswith("--")], []))
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
