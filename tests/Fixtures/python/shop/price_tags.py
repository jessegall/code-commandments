"""Shelf price tags and the promotion printed on them."""

from dataclasses import dataclass


@dataclass(frozen=True)
class PriceTag:
    cents: int
    # @sin PhantomNullable
    promotion: str | None = None

    def banner(self) -> str:
        return self.promotion.upper()


@dataclass(frozen=True)
class PromotedTag:
    cents: int
    # @fixed PhantomNullable
    promotion: str

    def banner(self) -> str:
        return self.promotion.upper()
