# Labels waiting to be printed, oldest first. The clock the queue keeps was wanted while each label carried the
# time it was queued; the label carries its own stamp now, and the clock is read nowhere.


class WaitingClock:
    def now(self) -> float:
        return 0.0


class LabelWaiting:
    def __init__(self, clock: WaitingClock) -> None:
        # @sin UnreadCollaborator
        self._clock = clock
        self._waiting: list[str] = []

    def add(self, label: str) -> None:
        self._waiting.append(label)

    def take(self) -> str:
        return self._waiting.pop(0)

    def waiting(self) -> int:
        return len(self._waiting)
