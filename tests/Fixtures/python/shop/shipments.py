# Packing reads the lines off the order it was handed, and settles in the header whether the order
# has any — a nullable field every reader has to defend. Below it, the FIX: the order always carries
# its lines, so packing asks nothing.
from dataclasses import dataclass, field


def pack(order, crate) -> int:
    packed = 0
    # @sin CoalescedLoopSubject
    for line in order.lines if order.lines is not None else ():
        crate.put(line.sku, line.quantity)
        packed += line.quantity
    return packed


# @fixed CoalescedLoopSubject
@dataclass(frozen=True)
class PackedOrder:
    number: str
    lines: tuple = field(default_factory=tuple)


# @fixed CoalescedLoopSubject
def pack_order(order: PackedOrder, crate) -> int:
    packed = 0
    for line in order.lines:
        crate.put(line.sku, line.quantity)
        packed += line.quantity
    return packed
