import enum
from typing import Final

LIMIT: Final = 3


# @contract fact/*/symbol on type -> suit_type
class Suit(enum.Enum): # @label suit_type type
    # @contract kind/enum-case
    # @contract role/enum-case/value -> hearts_val
    # @label hearts_val literal
    HEARTS = "H"
    SPADES = "S"


def color(suit: Suit) -> str:
    # @contract fact/dispatch/exhaustive = true
    match suit:
        case Suit.HEARTS:
            return "red"
        case Suit.SPADES:
            return "black"


def doubled() -> int:
    # @contract fact/*/constantValue on reference = 3
    return LIMIT * 2
