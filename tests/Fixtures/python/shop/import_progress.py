# An import's progress returned as a dict from counters a method was handed. Below it, the FIX: a
# progress value that also answers the percentage itself.
from dataclasses import dataclass


class StockImport:
    def __init__(self, rows: list) -> None:
        self.rows = rows

    def progress(self, done: int, failed: int) -> dict:
        # @sin DictReturnBag
        return {"done": done, "failed": failed, "left": len(self.rows) - done - failed}


# @fixed DictReturnBag
@dataclass(frozen=True)
class ImportProgress:
    done: int
    failed: int
    total: int

    @property
    def left(self) -> int:
        return self.total - self.done - self.failed
