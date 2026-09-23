# Look-alikes that default in a loop header and are right to: an object reading its own sparse
# registry answers "nobody listens" as an ordinary answer, and a call normalised on the spot fixes
# the absence where it is born.
from glob import glob


class Listeners:
    def __init__(self) -> None:
        self._by_event: dict = {}

    # @righteous CoalescedLoopSubject
    def notify(self, event: str, payload) -> None:
        for listener in self._by_event.get(event, []):
            listener(payload)


# @righteous CoalescedLoopSubject
def reports() -> list:
    return [path for path in glob("reports/*.csv") or []]


# @righteous CoalescedLoopSubject
def archive(folder: str) -> int:
    count = 0
    for path in glob(f"{folder}/*.log") or []:
        count += len(path)
    return count
