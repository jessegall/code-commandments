# A stock import reporting its progress through an optional callable, defaulted in its body with `or`.
# Below it, the FIX: the quiet default in the signature.
from typing import Callable, Optional


class StockImport:
    def __init__(self, rows: list) -> None:
        self.rows = rows

    # @sin NullableCallback
    def run(self, stock, progress: Optional[Callable[[int], None]] = None) -> None:
        report = progress or (lambda done: None)
        for done, row in enumerate(self.rows, start=1):
            stock.add(row)
            report(done)

    # @fixed NullableCallback
    def run_reporting(self, stock, progress: Callable[[int], None] = lambda done: None) -> None:
        for done, row in enumerate(self.rows, start=1):
            stock.add(row)
            progress(done)
