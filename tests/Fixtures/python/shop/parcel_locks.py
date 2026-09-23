# A parcel locker's door, whose open/shut state is asked as a bare verb. Below it, the FIX: the same
# body, asked as a question.


class LockerDoor:
    def __init__(self, bay: str, bolted: bool) -> None:
        self.bay = bay
        self.bolted = bolted

    # @sin BareStatePredicate
    def locks(self) -> bool:
        return self.bolted


# @fixed BareStatePredicate
class LockerHatch:
    def __init__(self, bay: str, bolted: bool) -> None:
        self.bay = bay
        self.bolted = bolted

    def is_locked(self) -> bool:
        return self.bolted
