# Order numbers counted on the class itself, every instance bumping the one shared total. Below it, the
# FIX: the counter an object the caller owns. And a look-alike: a memo filled once, which changes
# nothing a caller can observe.
import re

_SKU_PATTERN = None


class OrderNumbers:
    issued = 0

    def next(self) -> str:
        # @sin MutableStaticState
        OrderNumbers.issued += 1
        return f"ORD-{OrderNumbers.issued:06d}"


# @fixed MutableStaticState
class OrderNumberSequence:
    def __init__(self, start: int = 0) -> None:
        self.issued = start

    def next(self) -> str:
        self.issued += 1
        return f"ORD-{self.issued:06d}"


# @righteous MutableStaticState
def sku_pattern():
    global _SKU_PATTERN
    if _SKU_PATTERN is None:
        _SKU_PATTERN = re.compile(r"[A-Z]{3}-\d{4}")
    return _SKU_PATTERN
