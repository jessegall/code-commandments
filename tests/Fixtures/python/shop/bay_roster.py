# The bays a shift is worked from, in the order they were opened. Every caller asserts the answer away, which is
# the same lie written with an assert instead of a dereference.
class Bay:
    def __init__(self, number: str, opened_at: int) -> None:
        self.number = number
        self.opened_at = opened_at


class BayRoster:
    """The bays of one shift, oldest first, with the hours they opened at."""

    def __init__(self) -> None:
        self.bays: list[Bay] = []

    def open(self, bay: Bay) -> None:
        self.bays.append(bay)
        self.bays.sort(key=lambda each: each.opened_at)

    def opened_before(self, hour: int) -> list[Bay]:
        return [bay for bay in self.bays if bay.opened_at < hour]

    # @sin DeNulledFinder
    def numbered(self, number: str) -> Bay | None:
        for bay in self.bays:
            if bay.number == number:
                return bay

        return None
