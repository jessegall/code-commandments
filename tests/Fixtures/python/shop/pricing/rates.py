# Courier rates: a package the couriers import and that imports nothing back.


def rate_for(parcel: str) -> int:
    return 500 + len(parcel)
