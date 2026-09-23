# Courier zones where next-day delivery runs, listed as raw strings that are the zone enum's values.
# Below it, the FIX: the enum names the group.
from enum import StrEnum


class Zone(StrEnum):
    CITY = "city"
    SUBURB = "suburb"
    RURAL = "rural"

    @property
    def next_day(self) -> bool:
        return self in (Zone.CITY, Zone.SUBURB)


def next_day_possible(address) -> bool:
    # @sin InLiteralsMirrorsEnum
    return address.zone in ("city", "suburb")


# @fixed InLiteralsMirrorsEnum
def next_day_for(address) -> bool:
    return Zone(address.zone).next_day
