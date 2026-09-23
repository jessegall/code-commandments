# A retry loop that tells a listener about each attempt — when it was given one, asked every time. Below
# it, the FIX: a no-op listener as the default, called without asking.
from collections.abc import Callable


# @sin NullableCallback
def with_retries(work: Callable[[], bool], attempts: int, on_retry: Callable[[int], None] | None = None) -> bool:
    for attempt in range(attempts):
        if work():
            return True
        if on_retry is not None:
            on_retry(attempt)
    return False


def ignore(*_) -> None:
    pass


# @fixed NullableCallback
def retrying(work: Callable[[], bool], attempts: int, on_retry: Callable[[int], None] = ignore) -> bool:
    for attempt in range(attempts):
        if work():
            return True
        on_retry(attempt)
    return False
