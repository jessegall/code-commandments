# A currency rounder whose docstring insists there is nothing clever about it.


# @sin NegativeSpaceComment
class CurrencyRounder:
    """Rounds amounts to the currency's smallest unit. There is no magic here."""

    def __init__(self, places: int) -> None:
        self.places = places

    def round(self, amount: float) -> float:
        return round(amount, self.places)
