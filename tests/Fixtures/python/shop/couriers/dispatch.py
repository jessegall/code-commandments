# @example NamespaceCycle bad
# A courier dispatch that imports the tracking events module outright, while tracking imports the couriers.
# Beside it, a one-way import into a package that never imports back.
# @sin NamespaceCycle
import shop.tracking.events
# @righteous NamespaceCycle
from ..pricing.rates import rate_for


def dispatch(parcel: str) -> str:
    shop.tracking.events.record(parcel)
    return f"{parcel} at {rate_for(parcel)}"
