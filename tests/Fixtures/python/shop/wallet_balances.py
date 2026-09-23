# A wallet balance that adds to itself in place — every holder of the value sees it change. Below it,
# the FIX: a frozen value that derives the new balance.
from dataclasses import dataclass, replace


# @sin MutableValueObject
@dataclass
class Balance:
    cents: int
    currency: str

    def covers(self, price: int) -> bool:
        return self.cents >= price

    def shown(self) -> str:
        whole, rest = divmod(self.cents, 100)
        return f"{self.currency} {whole}.{rest:02d}"

    def deposit(self, cents: int) -> None:
        self.cents += cents


# @fixed MutableValueObject
@dataclass(frozen=True)
class Funds:
    cents: int
    currency: str

    def deposited(self, cents: int) -> "Funds":
        return replace(self, cents=self.cents + cents)
