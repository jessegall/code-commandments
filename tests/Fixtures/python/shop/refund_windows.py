# A refund window picked by an or-pattern over the channel enum's raw values. Below it, the FIX: a
# property on the enum. And look-alikes: a match on the members, and one inside the enum itself.
from enum import StrEnum


class SalesChannel(StrEnum):
    SHOP = "shop"
    WEB = "web"
    PHONE = "phone"

    @property
    def refund_days(self) -> int:
        match self.value:
            case "shop":
                return 14
            case _:
                return 30


def refund_days(order) -> int:
    # @sin EnumValueMatch
    match order.channel.value:
        case "web" | "phone":
            return 30
        case "shop":
            return 14


# @fixed EnumValueMatch
def refund_window(order) -> int:
    return order.channel.refund_days


# @righteous EnumValueMatch
def counter_staffed(order) -> bool:
    match order.channel:
        case SalesChannel.SHOP:
            return True
        case _:
            return False
