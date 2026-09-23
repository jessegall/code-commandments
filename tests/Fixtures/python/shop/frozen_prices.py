# A frozen price that discounts itself anyway, through `object.__setattr__`. Below it, the FIX: the
# discounted price is a new one.
from dataclasses import dataclass, replace


# @sin MutableValueObject
@dataclass(frozen=True)
class ShelfPrice:
    cents: int
    sku: str

    def discount(self, percent: int) -> None:
        object.__setattr__(self, "cents", self.cents * (100 - percent) // 100)

    def label(self) -> str:
        return f"{self.sku}: {self.cents / 100:.2f}"


# @fixed MutableValueObject
@dataclass(frozen=True)
class TagPrice:
    cents: int
    sku: str

    def discounted(self, percent: int) -> "TagPrice":
        return replace(self, cents=self.cents * (100 - percent) // 100)
