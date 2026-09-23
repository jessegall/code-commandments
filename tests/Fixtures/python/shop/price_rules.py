# A price rule re-rated through `type(self)`, still re-listing every field. Below it, the FIX: replace.
# And look-alikes: a copy that changes nothing, and a plain class that has no replace to offer.
from dataclasses import dataclass, replace


@dataclass(frozen=True)
class PriceRule:
    sku: str
    rate: float
    starts: str
    ends: str

    def applies_on(self, day: str) -> bool:
        return self.starts <= day <= self.ends

    def price_for(self, base: int) -> int:
        discounted = round(base * (1 - self.rate))
        return max(discounted, 0)

    def rerated(self, factor: float) -> "PriceRule":
        # @sin HandRolledReplace
        return type(self)(self.sku, min(self.rate * factor, 0.9), self.starts, self.ends)

    # @fixed HandRolledReplace
    def extended(self, ends: str) -> "PriceRule":
        return replace(self, ends=ends)

    # @righteous HandRolledReplace
    def duplicate(self) -> "PriceRule":
        return PriceRule(self.sku, self.rate, self.starts, self.ends)


# @righteous HandRolledReplace
class Coupon:
    def __init__(self, code: str, value: int, uses: int, owner: str) -> None:
        self.code, self.value, self.uses, self.owner = code, value, uses, owner

    def used(self) -> "Coupon":
        return Coupon(self.code, self.value, self.uses + 1, self.owner)
