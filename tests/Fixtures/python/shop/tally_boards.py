# A tally board whose counts live on the class, not on the board: the dict is made once, when the class is, so
# every board shares it and none of them is told. Below it, the FIX: each board makes its own in __init__.


class TallyBoard:
    counts: dict[str, int] = {}

    def count(self, line: str) -> None:
        # @sin MutableStaticState
        self.counts.update({line: self.counts.get(line, 0) + 1})

    def counted(self, line: str) -> int:
        return self.counts.get(line, 0)


# @fixed MutableStaticState
class OwnTallyBoard:
    def __init__(self, opened: str) -> None:
        self.opened = opened
        self.counts: dict[str, int] = {}

    def count(self, line: str) -> None:
        self.counts[line] = self.counts.get(line, 0) + 1

    def summary(self) -> str:
        counted = sum(self.counts.values())

        return f"{self.opened}: {counted} over {len(self.counts)} lines"
