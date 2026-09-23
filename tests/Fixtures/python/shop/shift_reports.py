"""The line a till prints about the shift that closed it."""

from dataclasses import dataclass


@dataclass(frozen=True)
class Cashier:
    name: str


@dataclass(frozen=True)
class Shift:
    cashier: Cashier
    takings: int


class TillPrinter:
    def closing_line(self, shift: Shift, cashier_name: str) -> str:
        return f"{cashier_name} closed with {shift.takings}"

    # @fixed DerivedArgument
    def closing_line_of(self, shift: Shift) -> str:
        return f"{shift.cashier.name} closed with {shift.takings}"


def close(printer: TillPrinter, shift: Shift) -> str:
    # @sin DerivedArgument
    return printer.closing_line(shift=shift, cashier_name=shift.cashier.name)
