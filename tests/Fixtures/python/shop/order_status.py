# An order's statuses kept as a class of string constants — a closed set nothing seals, so any string
# passes where a status belongs. Below it, the FIX: a `StrEnum`, the set a type of its own.
from enum import StrEnum


# @sin ConstantClassEnum
class OrderStatus:
    PENDING = "pending"
    PAID = "paid"
    SHIPPED = "shipped"


# @fixed ConstantClassEnum
class OrderState(StrEnum):
    PENDING = "pending"
    PAID = "paid"
    SHIPPED = "shipped"
