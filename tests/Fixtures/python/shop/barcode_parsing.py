# A scanned barcode parsed into its parts, returned as a parenthesised tuple of values from the scan
# and from the lookup. Below it, the FIX: a NamedTuple, each slot named.
from typing import NamedTuple


class Scanner:
    def __init__(self, catalog) -> None:
        self.catalog = catalog

    def parse(self, code: str):
        prefix, _, serial = code.partition("-")
        # @sin PositionalTupleReturn
        return (prefix, serial, self.catalog.sku_for(prefix))


# @fixed PositionalTupleReturn
class Scan(NamedTuple):
    prefix: str
    serial: str
    sku: str


# @fixed PositionalTupleReturn
class NamedScanner:
    def __init__(self, catalog) -> None:
        self.catalog = catalog

    def parse(self, code: str) -> Scan:
        prefix, _, serial = code.partition("-")
        return Scan(prefix, serial, self.catalog.sku_for(prefix))
