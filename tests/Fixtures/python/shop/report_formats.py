# A report's empty total built from literals by a property. Below it, the FIX: a class attribute. And
# look-alikes: a subclass answering its own format through the property it overrides, and live state.
import sys
from decimal import Decimal


class SalesReport:
    def __init__(self, rows: list) -> None:
        self.rows = rows

    # @sin ConstantProperty
    @property
    def opening_total(self) -> Decimal:
        return Decimal("0.00")

    def total(self) -> Decimal:
        return self.opening_total + sum((row.amount for row in self.rows), Decimal("0.00"))


# @fixed ConstantProperty
class StockReport:
    opening_total = Decimal("0.00")

    def __init__(self, rows: list) -> None:
        self.rows = rows


class Export:
    # @righteous ConstantProperty
    @property
    def extension(self) -> str:
        return "txt"


class CsvExport(Export):
    # @righteous ConstantProperty
    @property
    def extension(self) -> str:
        return "csv"


class ConsoleReport:
    # @righteous ConstantProperty
    @property
    def out(self):
        return sys.stdout
