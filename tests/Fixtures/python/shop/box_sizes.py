# A box chosen by matching a size enum's numbers, a wildcard catching the rest. Below it, the FIX: the
# enum knows its own box.
from enum import IntEnum


class ParcelSize(IntEnum):
    SMALL = 1
    MEDIUM = 2
    LARGE = 3

    @property
    def box(self) -> str:
        return "envelope" if self is ParcelSize.SMALL else "carton"


class Packer:
    def __init__(self, stock) -> None:
        self.stock = stock

    def box_for(self, parcel) -> str:
        # @sin EnumValueMatch
        match parcel.size.value:
            case 1:
                box = "envelope"
            case _:
                box = "carton"
        self.stock.take(box)
        return box

    # @fixed EnumValueMatch
    def pack(self, parcel) -> str:
        self.stock.take(parcel.size.box)
        return parcel.size.box
