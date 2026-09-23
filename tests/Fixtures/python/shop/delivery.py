# Booking a delivery and quoting one both take the address as three loose strings — one concept,
# threaded through two modules with no name. Below it, the FIX: the address is a type of its own.

from dataclasses import dataclass


# @sin DataClump
def book_delivery(street: str, city: str, postcode: str, carrier) -> str:
    return carrier.book(f"{street}, {postcode} {city}")


# @fixed DataClump
@dataclass(frozen=True)
class Address:
    street: str
    city: str
    postcode: str

    def line(self) -> str:
        return f"{self.street}, {self.postcode} {self.city}"


# @fixed DataClump
def schedule_delivery(address: Address, carrier) -> str:
    return carrier.book(address.line())
