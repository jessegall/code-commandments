# A damage report gathers what each inspector saw, aisle by aisle, and says when it is heavy enough to stop the
# line. The aisles live in a set the report builds as it is walked, and the getter at the bottom hands that set
# itself back, so anything holding it can add an aisle nobody inspected and the report will never know.

HEAVY = 4


class DamageReport:
    def __init__(self, raised_by: str, shift: str) -> None:
        self.raised_by = raised_by
        self.shift = shift
        self._aisles: set[str] = set()
        self._notes: list[str] = []

    def saw(self, aisle: str, note: str) -> None:
        self._aisles.add(aisle)
        if note:
            self._notes.append(f"{aisle}: {note}")

    def is_heavy(self) -> bool:
        return len(self._aisles) >= HEAVY

    def summary(self) -> str:
        heading = f"{self.raised_by} on {self.shift}"
        if not self._notes:
            return f"{heading}: nothing written down"

        return heading + "\n" + "\n".join(sorted(self._notes))

    # @sin HandedOutCollection
    def aisles(self) -> set[str]:
        return self._aisles
