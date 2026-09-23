# A parcel label printer whose keyword flag picks between two carriers' formats in one expression.


# @sin FlagArgument
def label_for(parcel_id: str, weight_grams: int, *, express: bool = False) -> str:
    return express_label(parcel_id) if express else standard_label(parcel_id, weight_grams)


def express_label(parcel_id: str) -> str:
    return f"EXP-{parcel_id}"


def standard_label(parcel_id: str, weight_grams: int) -> str:
    return f"STD-{parcel_id}-{weight_grams}g"
