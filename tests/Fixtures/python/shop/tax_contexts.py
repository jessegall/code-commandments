# A basket priced as if it shipped abroad, the region on the pricer swapped for the call and put back.
# Below it, the FIX: the region passed in. And look-alikes: a context manager whose job is the swap,
# and a counter read, changed and written back.
from contextlib import contextmanager


class Pricer:
    def __init__(self, rates, region: str) -> None:
        self.rates = rates
        self.region = region
        self.quoted = 0

    def price(self, basket) -> int:
        return sum(self.rates.price(line, self.region) for line in basket)

    # @sin ScratchStateRestore
    def price_abroad(self, basket, region: str) -> int:
        home = self.region
        self.region = region
        total = self.price(basket)
        self.region = home
        return total

    # @righteous ScratchStateRestore
    @contextmanager
    def shipping_to(self, region: str):
        home = self.region
        self.region = region
        yield self
        self.region = home

    # @righteous ScratchStateRestore
    def count_quote(self) -> int:
        quoted = self.quoted
        quoted += 1
        self.quoted = quoted
        return quoted


# @fixed ScratchStateRestore
class RegionPricer:
    def __init__(self, rates) -> None:
        self.rates = rates

    def price(self, basket, region: str) -> int:
        return sum(self.rates.price(line, region) for line in basket)
