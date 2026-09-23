"""Hanging signs over the aisles, printed from each aisle's catalogue entry."""

from dataclasses import dataclass


@dataclass(frozen=True)
class SignSpec:
    heading: str
    colour: str


@dataclass(frozen=True)
class Aisle:
    number: int
    spec: SignSpec

    # @fixed KeyedLookupEnvy
    def sign_heading(self) -> str:
        return self.spec.heading


class SignCatalogue:
    def __init__(self, entries: dict[int, SignSpec]) -> None:
        self.entries = entries

    def entry(self, number: int) -> SignSpec:
        return self.entries[number]


class SignPrinter:
    def __init__(self, catalogue: SignCatalogue) -> None:
        self.catalogue = catalogue

    # @sin KeyedLookupEnvy
    def heading(self, aisle: Aisle) -> str:
        return self.catalogue.entry(aisle.number).heading

    # @fixed KeyedLookupEnvy
    def heading_of(self, aisle: Aisle) -> str:
        return aisle.sign_heading()
