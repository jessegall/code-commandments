# A tally of picks per aisle, defaulted to a dict: every caller that omits it writes into the one dict the def
# was defined with, so yesterday's counts are still in it when today's shift starts counting.


# @sin MutableDefault
def tally(aisle: str, counts: dict[str, int] = {}) -> dict[str, int]:
    counts[aisle] = counts.get(aisle, 0) + 1
    return counts


# @righteous MutableDefault
def tally_from(aisle: str, counts: tuple[str, ...] = ()) -> int:
    return len([held for held in counts if held == aisle])
