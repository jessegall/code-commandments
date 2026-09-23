# An order badge coloured by matching the status enum's raw string values at the call site. Below it,
# the FIX: the enum answers its own colour, matching its members.
from enum import StrEnum


class PaymentStatus(StrEnum):
    PENDING = "pending"
    PAID = "paid"
    REFUNDED = "refunded"

    def colour(self) -> str:
        match self:
            case PaymentStatus.PENDING:
                return "amber"
            case PaymentStatus.PAID:
                return "green"
            case PaymentStatus.REFUNDED:
                return "grey"


def badge(order) -> str:
    # @sin EnumValueMatch
    match order.payment.value:
        case "pending":
            return "amber"
        case "paid":
            return "green"
        case "refunded":
            return "grey"


# @fixed EnumValueMatch
def badge_for(order) -> str:
    return order.payment.colour()
