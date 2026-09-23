# A till closed with an optional hook run afterwards, asked by truthiness. Below it, the FIX: a no-op
# hook as the default. And look-alikes: an optional callable only handed on, and an optional VALUE.
from collections.abc import Callable


def noop() -> None:
    pass


# @sin NullableCallback
def close_till(till, after: Callable[[], None] | None = None) -> float:
    total = till.count()
    if after:
        after()
    return total


# @fixed NullableCallback
def close_till_then(till, after: Callable[[], None] = noop) -> float:
    total = till.count()
    after()
    return total


# @righteous NullableCallback
def close_all(tills: list, after: Callable[[], None] | None = None) -> list:
    return [close_till(till, after) for till in tills]


# @righteous NullableCallback
def close_labelled(till, label: str | None = None) -> float:
    if label is not None:
        till.tag(label)
    return till.count()
