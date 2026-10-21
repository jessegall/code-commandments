# What an aisle's stocktake came to, worked out once from the lines the counter read out and kept in a dict of
# its own. Nothing adds to it afterwards: the class answers questions about the count. The property at the
# bottom hands the dict itself back, so a caller can write a figure the stocktake never saw. Below it, the
# look-alike that is not the sin: the same answer as a mapping nobody can write to.

from collections.abc import Mapping

SHORT = 0


class AisleCounts:
    def __init__(self, read_out: list[tuple[str, int]]) -> None:
        self._counted: dict[str, int] = {}
        for line, counted in read_out:
            self._counted[line] = self._counted.get(line, 0) + counted

    def short_lines(self) -> list[str]:
        return sorted(line for line, counted in self._counted.items() if counted == SHORT)

    def total(self) -> int:
        return sum(self._counted.values())

    # @sin HandedOutCollection
    @property
    def counted(self) -> dict[str, int]:
        return self._counted


# @righteous HandedOutCollection
class SealedCounts:
    def __init__(self, read_out: list[tuple[str, int]]) -> None:
        self._counted: dict[str, int] = {}
        for line, counted in read_out:
            self._counted[line] = self._counted.get(line, 0) + counted

    @property
    def counted(self) -> Mapping[str, int]:
        return self._counted
