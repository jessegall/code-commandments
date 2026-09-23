# A stock count returned as a tuple typed with a fixed shape — typed, but still known only by position.
# Below it, the FIX: a dataclass. And look-alikes: a declared sequence, and three reads of one object.
from dataclasses import dataclass


def count_stock(shelf, backroom) -> tuple[int, int, int]:
    # @sin PositionalTupleReturn
    return shelf.count(), backroom.count(), shelf.count() + backroom.count()


# @fixed PositionalTupleReturn
@dataclass(frozen=True)
class StockCount:
    shelf: int
    backroom: int

    @property
    def total(self) -> int:
        return self.shelf + self.backroom


# @righteous PositionalTupleReturn
def sku_numbers(first, second, third) -> tuple[int, ...]:
    return first.number, second.number, third.number


# @righteous PositionalTupleReturn
def dimensions(parcel):
    return parcel.length, parcel.width, parcel.height
