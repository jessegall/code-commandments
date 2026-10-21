# Walks a round of crates and keeps the reason each one is held back. Three objects are asked in the
# condition, each about something else, and what the three together MEAN has no name anywhere: a reader is
# left to assemble the clearance from an or-chain.
from shop.bay_loading import Crate, LoadingBay


class BayClearance:
    def __init__(self, sweeping: bool) -> None:
        self.sweeping = sweeping
        self.reasons: dict[str, str] = {}

    def walk(self, round_of: list[Crate], bay: LoadingBay) -> None:
        for crate in round_of:
            # @sin UnnamedCondition
            if not crate.packed or not bay.is_free() or self.sweeping:
                self.reasons[crate.reference] = f"bay {bay.number}"

    def summary(self) -> str:
        return ", ".join(f"{reference} held at {where}" for reference, where in self.reasons.items())
