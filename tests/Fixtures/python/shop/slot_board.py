# The righteous twin. The board answers `Slot | None` and ONE of its callers has something to do about a missing
# slot: it says so on the board instead. The annotation tells the truth, so the finder is left alone.
class Slot:
    def __init__(self, at: int) -> None:
        self.at = at


class SlotBoard:
    def __init__(self, slots: list[Slot]) -> None:
        self.slots = slots

    # @righteous DeNulledFinder
    def at(self, hour: int) -> Slot | None:
        return next((slot for slot in self.slots if slot.at == hour), None)
