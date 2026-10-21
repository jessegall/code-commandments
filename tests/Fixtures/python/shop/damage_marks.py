# Which aisles a damaged pallet has been seen in, kept in a default set built by a call, and the shelves a
# recall blocks, kept in a default comprehension. set() and the comprehension both run once, when the def is
# defined, which is the same fault as writing the braces out.


# @sin MutableDefault
def mark(aisle: str, seen: set[str] = set()) -> str:
    seen.add(aisle)

    return f"{len(seen)} aisles"


# @sin MutableDefault
def block(shelf: int, blocked: list[int] = [n for n in range(0)]) -> str:
    blocked.append(shelf)

    return f"{len(blocked)} shelves"
