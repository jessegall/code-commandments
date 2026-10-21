# Where a crate is looked up by its reference. The lookup answers `Crate | None`, and every caller reads straight
# through the answer: nobody has anything to do about a missing crate, so the absence is the registry's to decide.
# Below it, the FIX: it answers a crate or raises, and the callers read it plainly. The same lookup is the registry
# rule's sin too — a dict read that answers None — so it carries both marks: two rules, two fixes, one line.
class CrateMissing(Exception):
    def __init__(self, reference: str) -> None:
        super().__init__(f"no crate {reference}")
        self.reference = reference


class Crate:
    def __init__(self, reference: str, weight: int) -> None:
        self.reference = reference
        self.weight = weight


class CrateRegistry:
    """Every crate on the floor, by its reference, with the weight it was booked in at."""

    def __init__(self) -> None:
        self.kept: dict[str, Crate] = {}

    def record(self, crate: Crate) -> None:
        self.kept[crate.reference] = crate

    def booked(self) -> int:
        return sum(crate.weight for crate in self.kept.values())

    def references(self) -> list[str]:
        return sorted(self.kept)

    # @sin DeNulledFinder
    def find(self, reference: str) -> Crate | None:
        # @sin NullableRegistryLookup
        return self.kept.get(reference)

    # @fixed DeNulledFinder
    def crate(self, reference: str) -> Crate:
        found = self.kept.get(reference)
        if found is None:
            raise CrateMissing(reference)

        return found
