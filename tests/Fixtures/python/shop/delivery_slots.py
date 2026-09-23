# A delivery slot's fee picked by matching the slot's name as a string. Below it, the FIX: the slot
# arrives as the enum. And look-alikes: a match on the members, and on strings no enum holds.
from enum import StrEnum


class Slot(StrEnum):
    MORNING = "morning"
    EVENING = "evening"

    @property
    def fee(self) -> int:
        return 5 if self is Slot.EVENING else 0


def slot_fee(slot: str) -> int:
    # @sin StringMatchMirrorsEnum
    match slot:
        case "morning":
            return 0
        case "evening":
            return 5


# @fixed StringMatchMirrorsEnum
def slot_fee_for(slot: Slot) -> int:
    return slot.fee


# @righteous StringMatchMirrorsEnum
def slot_label(slot: Slot) -> str:
    match slot:
        case Slot.MORNING:
            return "08:00-12:00"
        case Slot.EVENING:
            return "18:00-22:00"


# @righteous StringMatchMirrorsEnum
def weekday_index(name: str) -> int:
    match name:
        case "saturday":
            return 5
        case "sunday":
            return 6
    return 0
