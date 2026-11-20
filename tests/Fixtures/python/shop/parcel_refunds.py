from dataclasses import dataclass


@dataclass
class ParcelReturn:
    reason: str
    cents: int
    opened: bool


@dataclass
class Shelf:
    seconds: int = 0
    fresh: int = 0


def restock(item: ParcelReturn, shelf: Shelf, log: list[str]) -> None:
    # @sin DuplicatedBranchTail
    if item.opened:
        shelf.seconds += 1
        log.append(item.reason)
    else:
        shelf.fresh += 1
        log.append(item.reason)


def refund(item: ParcelReturn) -> int:
    if item.cents <= 0:
        # @sin MessageAtThrow
        raise ValueError("nothing to refund")
        # @sin UnreachableStatement
        item.cents = 0

    return item.cents


# @fixed DuplicatedBranchTail
def restock_once(item: ParcelReturn, shelf: Shelf, log: list[str]) -> None:
    if item.opened:
        shelf.seconds += 1
    else:
        shelf.fresh += 1
    log.append(item.reason)


def refund_note(reason: str) -> str:
    # @sin ReassignedParameter
    reason = reason.strip().lower()
    return f"refund: {reason}"
