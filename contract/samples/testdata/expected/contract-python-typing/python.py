import enum
from typing import cast


class Suit(enum.Enum):
    HEARTS = "H"
    SPADES = "S"


type Pair[T] = tuple[T, T]


def first[T: int, U = str](items: list[T]) -> T:
    return items[0]


class Box[T]:
    pass


def check(value: object, other: object) -> int:
    if isinstance(value, int) and not isinstance(other, (str, bytes)):
        return cast(int, value)
    return 0
