# A dispatch desk filters parcels ready to leave in two places: a comprehension picking them and a
# predicate answering for one of them.


class Parcel:
    def __init__(self, labelled: bool, weight_grams: int, carrier: str) -> None:
        self.labelled = labelled
        self.weight_grams = weight_grams
        self.carrier = carrier


class DispatchDesk:
    def __init__(self, parcels: list[Parcel]) -> None:
        self.parcels = parcels

    def outgoing(self) -> list[Parcel]:
        # @sin RepeatedGuard
        return [parcel for parcel in self.parcels if parcel.labelled and parcel.weight_grams > 0]

    def may_leave(self, parcel: Parcel) -> bool:
        # @sin RepeatedGuard
        return parcel.labelled and parcel.weight_grams > 0
