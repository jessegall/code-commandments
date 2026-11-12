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

    def closures(self, modules: dict[str, tuple[str, list[str], dict[str, str]]]) -> dict[str, str]:
        """The key of everything each module reaches, in one pass over the import graph: a module is named by its
        contents' fingerprint, its imports of the project and the interfaces it sees from outside, and a group of
        modules that import each other shares one key made of all of theirs and of the groups they import. Each text
        is hashed once by the caller; a module never walks what it reaches again."""
        order, low, on_stack, stack, groups, counter = {}, {}, set(), [], [], [0]
        for start in sorted(modules):
            if start in order:
                continue
            work = [(start, iter(sorted(set(modules[start][1]) & modules.keys())))]
            order[start] = low[start] = counter[0]
            counter[0] += 1
            stack.append(start)
            on_stack.add(start)
            while work:
                node, edges = work[-1]
                advanced = False
                for nxt in edges:
                    if nxt not in order:
                        order[nxt] = low[nxt] = counter[0]
                        counter[0] += 1
                        stack.append(nxt)
                        on_stack.add(nxt)
                        work.append((nxt, iter(sorted(set(modules[nxt][1]) & modules.keys()))))
                        advanced = True
                        break
                    if nxt in on_stack:
                        low[node] = min(low[node], order[nxt])
                if advanced:
                    continue
                work.pop()
                if work:
                    low[work[-1][0]] = min(low[work[-1][0]], low[node])
                if low[node] == order[node]:
                    group = []
                    while True:
                        member = stack.pop()
                        on_stack.discard(member)
                        group.append(member)
                        if member == node:
                            break
                    groups.append(sorted(group))
        keys: dict[str, str] = {}
        for group in groups:
            digest = hashlib.sha256(self.version.encode())
            members = set(group)
            for member in group:
                sha, imports, outside = modules[member]
                digest.update(b"\0" + member.encode() + b"\0" + sha.encode())
                for name, interface in sorted(outside.items()):
                    digest.update(b"\0" + name.encode() + b"\0" + interface.encode())
            for reached in sorted({keys[i] for member in group for i in modules[member][1] if i in modules and i not in members}):
                digest.update(b"\0" + reached.encode())
            key = digest.hexdigest()
            for member in group:
                keys[member] = key
        return keys

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
        """A module's record: its line key, its imports of the project, the interfaces it saw from outside and the key
        of everything it reaches when its line was kept."""
        try:
            with open(self.path(record), "rb") as file:
                held = json.loads(zlib.decompress(file.read()))
            os.utime(self.path(record))
        except (OSError, ValueError, zlib.error):
            return None
        return held if isinstance(held, dict) and {"key", "imports", "outside", "closure"} <= held.keys() else None

    def record(self, record: str, key: str, imports: list[str], outside: dict[str, str], closure: str) -> None:
        """Keeps a module's record the moment its line is kept, so a run stopped part way keeps what it did."""
        self.stored(record, zlib.compress(json.dumps({"key": key, "imports": imports, "outside": outside, "closure": closure}).encode(), 3))

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
