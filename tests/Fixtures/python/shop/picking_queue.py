# A picking queue built by chaining. Sorting the queue is an order, narrated in the third person; the
# constraint beside it relates the queue to a zone, and the third person is right for that.
from typing import Self


class PickingQueue:
    def __init__(self) -> None:
        self.zone = ""
        self.key = "bin"

    # @sin NarratedCommand
    def sorts(self, key: str) -> Self:
        self.key = key
        return self

    # @righteous NarratedCommand
    def starts_in(self, zone: str) -> "PickingQueue":
        self.zone = zone
        return self
