from dataclasses import dataclass, field


@dataclass
class Parcel:
    zone: int
    grams: int
    labels: list[str] = field(default_factory=list)


# @sin FeatureEnvy
def label(parcel: Parcel) -> Parcel:
    # @sin DuplicatedBranchTail
    if parcel.grams > 2000:
        parcel.labels.append("heavy")
        parcel.zone = max(parcel.zone, 1)
    elif parcel.grams > 500:
        parcel.labels.append("medium")
        parcel.zone = max(parcel.zone, 1)
    else:
        parcel.zone = max(parcel.zone, 1)

    return parcel


def sorted_count(parcels: list[Parcel]) -> int:
    taken = 0
    for parcel in parcels:
        if parcel.grams > 30000:
            continue
            # @sin UnreachableStatement
            taken -= 1
        taken += 1

    return taken


# @righteous DuplicatedBranchTail
# @sin FeatureEnvy
def weigh(parcel: Parcel) -> Parcel:
    if parcel.grams > 2000:
        parcel.labels.append("heavy")
    parcel.zone = max(parcel.zone, 1)

    return parcel


def postage_cents(grams: int, zone: int) -> int:
    # @sin ReassignedParameter
    grams = max(grams, 100)
    return grams * zone


# @fixed ReassignedParameter
def billed_postage_cents(grams: int, zone: int) -> int:
    billed = max(grams, 100)
    return billed * zone


# @righteous ReassignedParameter
# @sin FeatureEnvy
def rezone(parcel: Parcel, zone: int) -> Parcel:
    parcel.zone = max(zone, 1)
    return parcel
