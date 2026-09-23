# A gift note kept as a dataclass field whose blank default means "no note", and every method asks
# the blank again. Below it, the FIX: the field says it may be missing.
from dataclasses import dataclass


@dataclass(frozen=True)
class GiftWrap:
    colour: str
    # @sin BlankStringDefault
    note: str = ""

    def card(self) -> str:
        return "(no card)" if self.note == "" else self.note

    def needs_card(self) -> bool:
        return self.note != ""


# @fixed BlankStringDefault
@dataclass(frozen=True)
class WrappedGift:
    colour: str
    note: str | None = None

    def card(self) -> str:
        return "(no card)" if self.note is None else self.note
