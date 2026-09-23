# A courier pickup that loads a parcel only when it may leave — the dispatch desk's question, copied
# here instead of asked by name.
from dispatch_desk import Parcel


def load(van: list[Parcel], parcel: Parcel) -> None:
    # @sin RepeatedGuard
    if parcel.labelled and parcel.weight_grams > 0:
        van.append(parcel)
