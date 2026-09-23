# A delivery address that moves in place, street and city rewritten on the object every order holds.
# Below it, the FIX: a new address. And look-alikes: counters kept out of init, and a memo filled once.
from dataclasses import dataclass, field, replace


# @sin MutableValueObject
@dataclass
class DeliveryAddress:
    street: str
    city: str
    postcode: str

    def envelope(self) -> list:
        return [self.street, f"{self.postcode}  {self.city.upper()}"]

    def in_city(self, city: str) -> bool:
        return self.city.casefold() == city.casefold()

    def move(self, street: str, city: str) -> None:
        self.street = street
        self.city = city


# @fixed MutableValueObject
@dataclass(frozen=True)
class ShippingAddress:
    street: str
    city: str
    postcode: str

    def moved(self, street: str, city: str) -> "ShippingAddress":
        return replace(self, street=street, city=city)


# @righteous MutableValueObject
@dataclass
class PickRound:
    orders: list
    picked: int = field(default=0, init=False)

    def pick(self) -> None:
        self.picked += 1


# @righteous MutableValueObject
@dataclass
class Invoice:
    number: str
    rendered: str | None = None

    def pdf(self, renderer) -> str:
        if self.rendered is None:
            self.rendered = renderer.render(self.number)
        return self.rendered
