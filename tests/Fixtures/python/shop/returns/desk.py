# The FIX for a cycle between returns and tracking: the returns desk imports tracking one way, and everything
# tracking needs about a return is declared in tracking.
# @fixed NamespaceCycle
from ..tracking.events import record


def book_return(parcel: str) -> list[str]:
    return record(f"return {parcel}")
