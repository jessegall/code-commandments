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
        digest = hashlib.sha256(self.version.encode())
        digest.update(hashlib.sha256(text).digest())
        digest.update(b"\0" + module.encode() + b"\0")
        if state is None:
            return digest.hexdigest()
        pending, seen = sorted(set(state.dependencies)), {state.id}
        while pending:
            dependency = pending.pop()
            if dependency in seen:
                continue
            seen.add(dependency)
            held = graph.get(dependency)
            digest.update(b"\0" + dependency.encode() + b"\0")
            own = texts.get(os.path.realpath(held.path)) if held is not None and held.path else None
            if own is None:
                digest.update(held.interface_hash if held is not None and held.interface_hash else b"")
                continue
            digest.update(hashlib.sha256(own).digest())
            pending += sorted(set(held.dependencies))
        return digest.hexdigest()

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
        path = self.path(key)
        os.makedirs(os.path.dirname(path), exist_ok=True)
        body = zlib.compress(json.dumps({"line": kept.line, "expressions": kept.expressions, "typed": kept.typed,
                                         "unjoined": kept.unjoined}, ensure_ascii=False, separators=(",", ":")).encode(
            "utf-8", errors="replace"), 3)
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
