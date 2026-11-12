"""The file lines an earlier run wrote, kept on disk under a key of everything a line is made from, so a run that finds
a module unchanged writes it from what was kept and mypy compiles only the modules that changed."""

from __future__ import annotations

import hashlib
import json
import os
import sys
import tempfile
import time
import zlib
from dataclasses import dataclass

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


RENEWED_AFTER = float(os.environ.get("COMMANDMENTS_CACHE_HOURS") or 12) * 3600 / 10
"""How long a record goes untouched before reading it renews its time to live: a tenth of the hours a cache is kept."""


@dataclass(frozen=True)
class KeptLine:
    """A module's file line as it is written, without its path or context: the members of its object as JSON text,
    ready to be put after them, and the counts its trailer adds up."""

    members: str
    expressions: int
    typed: int
    unjoined: int


class Kept:
    """The kept lines of one project, one file each, named by the key of what made it."""

    def __init__(self, folder: str) -> None:
        self.folder = folder
        self.version = bridge_version()
        os.makedirs(folder, exist_ok=True)

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

    def record_of(self, module: str, sha: bytes, pins: str) -> str:
        """Where a module's record is kept: by the module, the fingerprint of its contents and the pins, never its
        path."""
        return "record-" + hashlib.sha256(f"{self.version}\0{module}\0{pins}\0".encode() + sha).hexdigest()

    def recorded(self, record: str) -> tuple[dict, KeptLine] | None:
        """A module's record and its line, in one read: the interface each module it depends on showed when the
        line was made, and the interface it showed itself."""
        path = self.path(record)
        try:
            with open(path, "rb") as file:
                written = zlib.decompress(file.read()).decode("utf-8")
                if time.time() - os.fstat(file.fileno()).st_mtime > RENEWED_AFTER:
                    os.utime(path)
            meta, members = written.split("\n", 1)
            held = json.loads(meta)
        except (OSError, ValueError, zlib.error):
            return None
        if not isinstance(held, dict) or not {"imports", "outside", "interface", "counts"} <= held.keys():
            return None
        return held, KeptLine(members, *held["counts"])

    def record(self, record: str, held: dict, line: KeptLine) -> None:
        """Keeps a module's record with its line the moment the line is made, so a run stopped part way keeps what
        it did."""
        meta = json.dumps({**held, "counts": [line.expressions, line.typed, line.unjoined]})
        self.stored(record, zlib.compress((meta + "\n" + line.members).encode("utf-8", errors="replace"), 3))

    def path(self, key: str) -> str:
        return os.path.join(self.folder, key[:2], key[2:])

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
