# The announcer that says the next run out loud. Two parts of the condition are the announcer's own state
# and one is the tick's, so it is a sentence about the announcer that the announcer never says: it belongs
# on it, under a name.
from dataclasses import dataclass


@dataclass(frozen=True)
class ShiftTick:
    at: int


class TickAnnouncer:
    def __init__(self, automatic: bool, since: int) -> None:
        self.automatic = automatic
        self.since = since
        self.said = 0
        self.last = "nothing yet"

    def on_ticked(self, tick: ShiftTick, nxt: str | None) -> None:
        # @sin UnnamedCondition
        if self.automatic and not self.said and tick.at > self.since and nxt is not None:
            self.said += 1
            self.last = nxt

    def heard(self) -> str:
        if self.said == 0:
            return "silent"

        return f"said {self.said} times, last {self.last}"
