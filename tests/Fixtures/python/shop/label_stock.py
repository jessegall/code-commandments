# Which label stocks a thermal printer can take is a tuple of their names beside the stocks, asked
# inside the comprehension that filters them. The stock does not know what it can be printed on.
from dataclasses import dataclass


@dataclass(frozen=True)
class LabelStock:
    kind: str
    width_mm: int


STOCKS = (
    LabelStock("continuous", 102),
    LabelStock("die_cut", 102),
    LabelStock("fanfold", 148),
)

# @sin SideTableTrait
THERMAL = ("continuous", "die_cut")


def printable_on_thermal() -> list:
    return [stock for stock in STOCKS if stock.kind in THERMAL]
