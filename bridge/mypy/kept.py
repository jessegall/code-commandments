"""The file lines an earlier run wrote, kept on disk under a key of everything a line is made from, so a run that finds
a module unchanged writes it from what was kept and mypy compiles only the modules that changed."""

from __future__ import annotations

import hashlib
import json
import os
import sys
import tempfile
import zlib
from dataclasses import dataclass

from mypy.build import State
from mypy.version import __version__ as MYPY_VERSION

HERE = os.path.dirname(os.path.abspath(__file__))


def bridge_version() -> str:
    """The hash of the bridge's own code, the mypy and the Python running it, so a new bridge never reads a line an
    older one wrote."""
    digest = hashlib.sha256(f"{MYPY_VERSION}\0{sys.version}".encode())
    for name in sorted(os.listdir(HERE)):
        if name.endswith(".py"):
            with open(os.path.join(HERE, name), "rb") as file:
                digest.update(file.read())
    return digest.hexdigest()


@dataclass(frozen=True)
class KeptLine:
    """A module's file line without its path or context, and the counts its trailer adds up."""

    line: dict[str, object]
    expressions: int
    typed: int
    unjoined: int


class Kept:
    """The kept lines of one project, one file each, named by the key of what made it."""

    def __init__(self, folder: str) -> None:
        self.folder = folder
        self.version = bridge_version()
        os.makedirs(folder, exist_ok=True)

    def key(self, module: str, state: State | None, graph: dict[str, State], text: bytes, texts: dict[str, bytes]) -> str:
        """The module's name and own text, and everything it reaches: a module of the project by its text, however
        deep, and a module from outside by the interface mypy saw. mypy's interface of a project module carries its
        path, so it would differ in every checkout; its text does not."""
        project, outside = self.reached(state, graph, texts)
        digest = hashlib.sha256(self.version.encode())
        digest.update(hashlib.sha256(text).digest())
        digest.update(b"\0" + module.encode() + b"\0")
        for name, sha in sorted(project.items()):
            digest.update(b"\0" + name.encode() + b"\0" + sha.encode())
        for name, interface in sorted(outside.items()):
            digest.update(b"\0" + name.encode() + b"\0" + interface)
        return digest.hexdigest()

    def reached(self, state: State | None, graph: dict[str, State], texts: dict[str, bytes]) -> tuple[dict[str, str], dict[str, bytes]]:
        """Every module $state reaches, however deep: a module of the project by the fingerprint of its text, one
        from outside by the interface mypy saw."""
        project: dict[str, str] = {}
        outside: dict[str, bytes] = {}
        if state is None:
            return project, outside
        pending, seen = sorted(set(state.dependencies)), {state.id}
        while pending:
            dependency = pending.pop()
            if dependency in seen:
                continue
            seen.add(dependency)
            held = graph.get(dependency)
            own = texts.get(os.path.realpath(held.path)) if held is not None and held.path else None
            if own is None:
                outside[dependency] = held.interface_hash if held is not None and held.interface_hash else b""
                continue
            project[dependency] = hashlib.sha256(own).hexdigest()
            pending += sorted(set(held.dependencies))
        return project, outside

    def pins(self, base: str) -> str:
        """The fingerprint of the files that pin what is installed, so an installed package that changed starts
        every record over."""
        digest = hashlib.sha256()
        for pinned in ("pyproject.toml", "requirements.txt", "poetry.lock", "uv.lock", "Pipfile.lock"):
            try:
                with open(os.path.join(base, pinned), "rb") as file:
                    digest.update(pinned.encode() + b"\0" + file.read())
            except OSError:
                continue
        return digest.hexdigest()

    def record_of(self, module: str, text: bytes, pins: str) -> str:
        """Where a module's record is kept: by the module, its contents and the pins, never its path."""
        return "record-" + hashlib.sha256(f"{self.version}\0{module}\0{pins}\0".encode() + hashlib.sha256(text).digest()).hexdigest()

    def recorded(self, record: str) -> dict | None:
        """The line key a module's record holds and the contents of every project module its line was made against."""
        try:
            with open(self.path(record), "rb") as file:
                held = json.loads(zlib.decompress(file.read()))
            os.utime(self.path(record))
        except (OSError, ValueError, zlib.error):
            return None
        return held if isinstance(held, dict) and "key" in held and "reached" in held else None

    def record(self, record: str, key: str, reached: dict[str, str]) -> None:
        """Keeps a module's record the moment its line is kept, so a run stopped part way keeps what it did."""
        self.stored(record, zlib.compress(json.dumps({"key": key, "reached": reached}).encode(), 3))

    def path(self, key: str) -> str:
        return os.path.join(self.folder, key[:2], key[2:])

    def has(self, key: str) -> bool:
        return os.path.exists(self.path(key))

    def read(self, key: str) -> KeptLine | None:
        path = self.path(key)
        try:
            with open(path, "rb") as file:
                held = json.loads(zlib.decompress(file.read()))
            os.utime(path)
        except (OSError, ValueError, zlib.error):
            return None
        return KeptLine(held["line"], held["expressions"], held["typed"], held["unjoined"])

    def write(self, key: str, kept: KeptLine) -> None:
        self.stored(key, zlib.compress(json.dumps({"line": kept.line, "expressions": kept.expressions, "typed": kept.typed,
                                                   "unjoined": kept.unjoined}, ensure_ascii=False, separators=(",", ":")).encode(
            "utf-8", errors="replace"), 3))

    def stored(self, key: str, body: bytes) -> None:
        path = self.path(key)
        os.makedirs(os.path.dirname(path), exist_ok=True)
        handle, draft = tempfile.mkstemp(dir=os.path.dirname(path), prefix=".writing-")
        try:
            with os.fdopen(handle, "wb") as file:
                file.write(body)
            os.replace(draft, path)
        except OSError:
            try:
                os.remove(draft)
            except OSError:
                pass
