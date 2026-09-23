# A parcel's kind answered by a property that never looks at the parcel. Below it, the FIX: a class
# attribute, stored because it is stored.


class Envelope:
    def __init__(self, weight: float) -> None:
        self.weight = weight

    # @sin ConstantProperty
    @property
    def kind(self) -> str:
        return "envelope"


# @fixed ConstantProperty
class Letter:
    kind = "envelope"

    def __init__(self, weight: float) -> None:
        self.weight = weight
