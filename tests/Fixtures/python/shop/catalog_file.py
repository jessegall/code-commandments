# Reading the product catalog from disk: any failure at all — a missing file, broken JSON, a bug in
# the reader — comes back as an empty catalog nobody can tell from a real one. Below it, the FIX: the
# one failure that is expected is named, raised with its cause, and everything else propagates.

import json
from dataclasses import dataclass
from pathlib import Path


# @fixed SwallowedException
@dataclass(frozen=True)
class Catalog:
    products: dict


# @fixed SwallowedException
class CatalogUnreadable(Exception):
    @classmethod
    def at(cls, path: Path) -> "CatalogUnreadable":
        return cls(f"the catalog at {path} is not valid JSON")


def load_catalog(path: Path):
    try:
        return Catalog(json.loads(path.read_text()))
    # @sin SwallowedException
    except Exception:
        return {}


# @fixed SwallowedException
def read_catalog(path: Path) -> Catalog:
    try:
        return Catalog(json.loads(path.read_text()))
    except json.JSONDecodeError as error:
        raise CatalogUnreadable.at(path) from error
