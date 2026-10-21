# The hours a bay is worked. Both conditions here are the righteous twins of a concept with no name: one is
# a range check, three parts about ONE subject, which is a single idea already; the other is a guard standing
# at the top of its body, which says what it means by leaving.
from shop.bay_loading import Crate, LoadingBay


class BayWindow:
    def __init__(self, opens: int, closes: int, staffed: bool) -> None:
        self.opens = opens
        self.closes = closes
        self.staffed = staffed
        self.loaded = 0

    # @righteous UnnamedCondition
    def covers(self, at: int) -> bool:
        if at >= self.opens and at <= self.closes and self.staffed:
            return True

        return False

    # @righteous UnnamedCondition
    def load(self, crate: Crate, bay: LoadingBay) -> None:
        """Puts a crate in the bay, once everything about it allows that."""
        if not crate.packed or not bay.is_free() or not self.staffed:
            return

        bay.take(crate)
        self.loaded += 1
