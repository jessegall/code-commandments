# A blocked aisle discarded from the set the loop walks, and a shelf appended to the list it walks: one shrinks
# the set under the iterator, the other grows the list it is reading, which never ends while a shelf keeps
# naming the next. Below them, the FIX for each: a copy to walk, and a list to add to.


def unblock(blocked: set[str], opened: set[str]) -> None:
    for aisle in blocked:
        if aisle in opened:
            # @sin CollectionMutatedWhileIterated
            blocked.discard(aisle)


# @fixed CollectionMutatedWhileIterated
def unblock_opened(blocked: set[str], opened: set[str]) -> None:
    for aisle in list(blocked):
        if aisle in opened:
            blocked.discard(aisle)


def restock(shelves: list[str], below: list[str]) -> list[str]:
    for shelf in shelves:
        if shelf in below:
            # @sin CollectionMutatedWhileIterated
            shelves.append(f"{shelf}-reserve")

    return shelves
