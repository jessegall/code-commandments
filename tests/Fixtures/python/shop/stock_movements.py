# A stock movement is a base class asking which of its own subclasses it is, to decide how the
# warehouse count moves.


class Movement:
    def __init__(self, sku: str, units: int) -> None:
        self.sku = sku
        self.units = units

    def delta(self) -> int:
        # @sin TypeSwitch
        if isinstance(self, Receipt):
            return self.units
        elif isinstance(self, Dispatch):
            return -self.units
        else:
            return 0


class Receipt(Movement):
    pass


class Dispatch(Movement):
    pass
