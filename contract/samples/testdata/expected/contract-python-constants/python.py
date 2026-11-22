import enum
from typing import Final

LIMIT: Final = 3


class Suit(enum.Enum):
    HEARTS = "H"
    SPADES = "S"


def color(suit: Suit) -> str:
    match suit:
        case Suit.HEARTS:
            return "red"
        case Suit.SPADES:
            return "black"


def doubled() -> int:
    return LIMIT * 2
