# The handover one shift writes for the next, timed once at definition, so the whole run of shifts reads as
# having happened the moment the module loaded.

import time

LATE = 60.0


# @sin FrozenDefault
def hand_over(notes: str, at: float = time.time()) -> str:
    return f"{notes} ({at:.0f})"


def is_late(at: float, now: float) -> bool:
    return now - at > LATE
