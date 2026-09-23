# A courier manifest whose docstring points at a parcel class the shop no longer has. Below it, the FIX: the
# reference repointed at the class that exists.


# @sin DanglingDocReference
def manifest(parcels: list) -> str:
    """One line per :class:`shop.dispatch_desk.Package` handed to the courier."""
    return "\n".join(str(parcel) for parcel in parcels)


# @fixed DanglingDocReference
def manifest_lines(parcels: list) -> str:
    """One line per :class:`shop.dispatch_desk.Parcel` handed to the courier."""
    lines = [f"{index}. {parcel}" for index, parcel in enumerate(parcels, start=1)]
    return "\n".join(lines)
