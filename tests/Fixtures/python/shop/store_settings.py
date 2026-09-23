# The store's settings read from a file and returned as the decoded dict. Below it, the FIX: a settings
# value built at the boundary.
import json
from dataclasses import dataclass
from pathlib import Path


class SettingsFile:
    def __init__(self, path: Path) -> None:
        self.path = path

    def read(self):
        with self.path.open() as handle:
            # @sin RawDecodedReturn
            return json.load(handle)


# @fixed RawDecodedReturn
@dataclass(frozen=True)
class StoreSettings:
    name: str
    currency: str

    @classmethod
    def read(cls, path: Path) -> "StoreSettings":
        raw = json.loads(path.read_text())
        return cls(name=raw["name"], currency=raw["currency"])
