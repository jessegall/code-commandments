# A handover receipt inside the couriers package, which reads the dispatch module beside it. Its own package is
# couriers, so the import below and the registry's import of this module are both inside couriers.
from shop.couriers.dispatch import dispatch


def receipt_for(parcel: str) -> str:
    return f"handed over: {dispatch(parcel)}"
