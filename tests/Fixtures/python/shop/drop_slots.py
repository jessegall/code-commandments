"""Drop-off slots a customer can pick at checkout."""

from enum import StrEnum


class Slot(StrEnum):
    MORNING = "morning"
    EVENING = "evening"


class DropOff:
    def __init__(self, slot: str, day: str) -> None:
        self.slot = slot
        self.day = day

    @classmethod
    def booked(cls, slot: str, day: str) -> "DropOff":
        return cls(slot, day)


def morning_drop(day: str) -> DropOff:
    return DropOff.booked(Slot.MORNING, day)


def evening_drop(day: str) -> DropOff:
    # @sin UnnamedVocabularyLiteral
    return DropOff.booked("evening", day)
