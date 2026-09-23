# A stock level checked against two members with the member written first. Below it, the FIX: the
# enum names the group. And look-alikes: one member, and one member each for two subjects.
from enum import IntEnum


class ShelfLevel(IntEnum):
    EMPTY = 0
    LOW = 1
    PLENTY = 2

    @property
    def needs_order(self) -> bool:
        return self in (ShelfLevel.EMPTY, ShelfLevel.LOW)


def reorder(product, supplier) -> None:
    # @sin EnumCaseOrChain
    if ShelfLevel.EMPTY == product.level or ShelfLevel.LOW == product.level:
        supplier.order(product.sku)


# @fixed EnumCaseOrChain
def reorder_short(product, supplier) -> None:
    if product.level.needs_order:
        supplier.order(product.sku)


# @righteous EnumCaseOrChain
def sold_out(product) -> bool:
    return product.level == ShelfLevel.EMPTY


# @righteous EnumCaseOrChain
def either_empty(shelf, backroom) -> bool:
    return shelf.level == ShelfLevel.EMPTY or backroom.level == ShelfLevel.EMPTY
