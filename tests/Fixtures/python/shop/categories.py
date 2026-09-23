# A category tree walked from an index of children by parent id — and the walk decides, in its own
# `for` header, whether a parent has children at all. Below it, the FIX: the index answers for every
# parent, so the walk only walks.
from collections import defaultdict


def descendants(below: dict, parent: str) -> list:
    found = []
    # @sin CoalescedLoopSubject
    for child in below.get(parent, []):
        found.append(child)
        found.extend(descendants(below, child))
    return found


# @fixed CoalescedLoopSubject
def children_index(pairs) -> defaultdict:
    below = defaultdict(list)
    for parent, child in pairs:
        below[parent].append(child)
    return below


# @fixed CoalescedLoopSubject
def descendants_of(below: defaultdict, parent: str) -> list:
    found = []
    for child in below[parent]:
        found.append(child)
        found.extend(descendants_of(below, child))
    return found
