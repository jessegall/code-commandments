# Whether a courier charges for a weekend collection is held as a frozenset of the ones that do,
# read back at the one place that asks. The courier itself cannot answer the question about itself.
from dataclasses import dataclass


@dataclass(frozen=True)
class Haulier:
    code: str
    collects_on: str


HAULIERS = [
    Haulier("swiftpost", "weekdays"),
    Haulier("harbour", "weekends"),
    Haulier("nightowl", "weekends"),
]

# @sin SideTableTrait
WEEKEND_SURCHARGE = frozenset({"harbour", "nightowl"})


def quote(code: str, pence: int) -> int:
    if code in WEEKEND_SURCHARGE:
        return pence + 250
    return pence
