# Loyalty tiers checked against a set of the tier enum's values. Below it, the FIX: the enum names the
# group. And look-alikes: members tested directly, and strings no enum holds.
from enum import StrEnum


class Tier(StrEnum):
    BRONZE = "bronze"
    SILVER = "silver"
    GOLD = "gold"

    @property
    def earns_points(self) -> bool:
        return self in (Tier.SILVER, Tier.GOLD)


def points_for(customer, amount: float) -> int:
    # @sin InLiteralsMirrorsEnum
    if customer.tier in {"silver", "gold"}:
        return int(amount)
    return 0


# @fixed InLiteralsMirrorsEnum
def points_earned(customer, amount: float) -> int:
    if Tier(customer.tier).earns_points:
        return int(amount)
    return 0


# @righteous InLiteralsMirrorsEnum
def top_tier(customer) -> bool:
    return customer.tier in (Tier.GOLD, Tier.SILVER)


# @righteous InLiteralsMirrorsEnum
def is_card_payment(method: str) -> bool:
    return method in ("visa", "mastercard")
