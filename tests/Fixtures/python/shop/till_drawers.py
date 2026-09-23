"""Till drawers and the float each shift starts with."""

from dataclasses import dataclass


@dataclass(frozen=True)
class Float:
    cents: int

    def is_short(self, counted: int) -> bool:
        return counted < self.cents


@dataclass(frozen=True)
class Shift:
    drawer: str
    counted: int


class FloatBook:
    def __init__(self) -> None:
        self.floats: dict[str, Float] = {}

    def float_for(self, drawer: str) -> Float:
        return self.floats[drawer]


class CashOffice:
    def __init__(self, book: FloatBook) -> None:
        self.book = book

    # @sin KeyedLookupEnvy
    def came_up_short(self, shift: Shift) -> bool:
        if shift.counted == 0:
            return True
        return self.book.float_for(shift.drawer).is_short(shift.counted)
