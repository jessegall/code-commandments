# The returns desk accepts a parcel in three of its states, tested one by one with `is`. Below it, the
# FIX: the enum says which states a return may start from.
from enum import Enum


class ParcelState(Enum):
    DELIVERED = 1
    COLLECTED = 2
    REFUSED = 3
    LOST = 4

    def accepts_return(self) -> bool:
        return self in (ParcelState.DELIVERED, ParcelState.COLLECTED, ParcelState.REFUSED)


class ReturnsCounter:
    def __init__(self, ledger) -> None:
        self.ledger = ledger

    def open_return(self, parcel) -> None:
        # @sin EnumCaseOrChain
        if parcel.state is ParcelState.DELIVERED or parcel.state is ParcelState.COLLECTED or parcel.state is ParcelState.REFUSED:
            self.ledger.open(parcel.number)

    # @fixed EnumCaseOrChain
    def start_return(self, parcel) -> None:
        if parcel.state.accepts_return():
            self.ledger.open(parcel.number)
