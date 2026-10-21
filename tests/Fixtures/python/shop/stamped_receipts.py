# A receipt stamped with the moment it was issued. The default calls the clock once, when the def is defined,
# so every receipt the process prints carries the moment the module was imported. Below it, the FIX: the
# default says nothing, and the clock is read inside the body where each call reaches it.

from datetime import datetime


# @sin FrozenDefault
def stamp_receipt(order: str, at: datetime = datetime.now()) -> str:
    return f"{order} at {at.isoformat()}"


# @fixed FrozenDefault
def stamp_receipt_now(order: str, at: datetime | None = None) -> str:
    issued = at if at is not None else datetime.now()

    return f"{order} at {issued.isoformat()}"
