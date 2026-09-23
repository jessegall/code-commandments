# A freight band chosen by weight, the thresholds strung along one assignment and the band handed on
# into a call. Below it, the FIX: the thresholds as a table the code reads in order.

BANDS = ((20, "heavy"), (5, "medium"), (0, "light"))


def quote(parcel, carrier) -> float:
    # @sin NestedConditional
    band = "heavy" if parcel.kg > 20 else "medium" if parcel.kg > 5 else "light"
    return carrier.price(band, parcel.kg)


# @fixed NestedConditional
def quote_banded(parcel, carrier) -> float:
    band = next(name for floor, name in BANDS if parcel.kg > floor or floor == 0)
    return carrier.price(band, parcel.kg)
