# A tax table kept as a class attribute that a classmethod replaces for everyone. Below it, the FIX:
# the rates on an instance, passed to whatever prices with them.


class TaxTable:
    rates: dict = {}

    @classmethod
    def load(cls, rates: dict) -> None:
        # @sin MutableStaticState
        cls.rates = rates


# @fixed MutableStaticState
class TaxRates:
    def __init__(self, rates: dict) -> None:
        self.rates = rates

    def rate_for(self, region: str) -> float:
        return self.rates[region]
