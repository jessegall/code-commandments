# Loading a crate out of a bay. The condition asks the crate two things and the bay a third, and a reader
# has to hold all three in mind to see that it means "ready to load". Below it, the FIX: the crate answers
# that question under its own name, and each object answers for its own part of it.
class Crate:
    def __init__(self, reference: str, packed: bool, labelled: bool) -> None:
        self.reference = reference
        self.packed = packed
        self.labelled = labelled

    # @fixed UnnamedCondition
    def ready_for(self, bay: "LoadingBay") -> bool:
        return self.packed and self.labelled and bay.is_free()


class LoadingBay:
    def __init__(self, number: str) -> None:
        self.number = number
        self.standing: Crate | None = None

    def take(self, crate: Crate) -> None:
        self.standing = crate

    def is_free(self) -> bool:
        return self.standing is None


class BayDispatcher:
    def __init__(self, bay: LoadingBay) -> None:
        self.bay = bay
        self.sent: list[str] = []

    def dispatch(self, crate: Crate) -> None:
        # @sin UnnamedCondition
        if crate.packed and crate.labelled and self.bay.is_free():
            self.bay.take(crate)
            self.sent.append(crate.reference)

    # @fixed UnnamedCondition
    def dispatch_told(self, crate: Crate) -> None:
        if crate.ready_for(self.bay):
            self.bay.take(crate)
            self.sent.append(crate.reference)
