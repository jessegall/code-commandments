"""Stock moved between shelves, logged with the shelf it came from."""

from dataclasses import dataclass


@dataclass(frozen=True)
class Shelf:
    code: str
    aisle: int


def log_move(shelf: Shelf, shelf_code: str, units: int) -> str:
    return f"{shelf_code}: {units} from aisle {shelf.aisle}"


# @fixed DerivedArgument
def log_move_from(shelf: Shelf, units: int) -> str:
    return f"{shelf.code}: {units} from aisle {shelf.aisle}"


def restock(shelf: Shelf, units: int) -> str:
    # @sin DerivedArgument
    return log_move(shelf, shelf.code, units)


def empty_out(shelves: list[Shelf]) -> list[str]:
    moved = []
    for shelf in shelves:
        # @sin DerivedArgument
        moved.append(log_move(shelf, shelf_code=shelf.code, units=0))
    return moved


# @fixed DerivedArgument
def restock_honestly(shelf: Shelf, units: int) -> str:
    return log_move_from(shelf, units)
