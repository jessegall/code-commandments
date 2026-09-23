# Putting an order on hold and releasing it queue the same request to the warehouse, apart from the
# label shown and the action sent.

HOLD = "hold"
RELEASE = "release"


# @sin NearDuplicateFunction
def hold_order(warehouse, number: str) -> dict:
    found = warehouse.locate(number)
    queued = warehouse.queue(number, "Put on hold", site=found["site"], action=HOLD)
    return {key: value for key, value in queued.items() if key != "line"} | {"queued": True}


# @sin NearDuplicateFunction
def release_order(warehouse, number: str) -> dict:
    found = warehouse.locate(number)
    queued = warehouse.queue(number, "Release to picking", site=found["site"], action=RELEASE)
    return {key: value for key, value in queued.items() if key != "line"} | {"queued": True}
