# @example NamespaceCycle bad
# Tracking events, which know every courier and the dispatch step they came from.
from shop.couriers import dispatch
from shop.couriers.dispatch import dispatch as sent


def record(parcel: str) -> list[str]:
    return [parcel, dispatch.__name__, sent.__name__]
