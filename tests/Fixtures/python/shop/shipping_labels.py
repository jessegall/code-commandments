# A shipping label built from a dict of the parcel's fields, read key by key — a record nobody
# declared. Below it, the FIX: the record is a frozen dataclass, built once where the data enters.

from dataclasses import dataclass
from typing import Any


def label(parcel: dict[str, Any]) -> str:
    # @sin DictBag
    return f"{parcel['recipient']}\n{parcel['street']}\n{parcel['postcode']} {parcel['city']}"


# @fixed DictBag
@dataclass(frozen=True)
class Parcel:
    recipient: str
    street: str
    postcode: str
    city: str

    @classmethod
    def from_payload(cls, payload: dict[str, Any]) -> "Parcel":
        return cls(payload["recipient"], payload["street"], payload["postcode"], payload["city"])

    def label(self) -> str:
        return f"{self.recipient}\n{self.street}\n{self.postcode} {self.city}"
