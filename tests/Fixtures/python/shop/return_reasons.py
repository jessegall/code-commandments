# A returns form's reason matched as strings on an attribute, with an or-pattern and a wildcard. Below
# it, the FIX: the reason is the enum on the form, and the enum answers.
from enum import StrEnum


class ReturnReason(StrEnum):
    DAMAGED = "damaged"
    WRONG_ITEM = "wrong_item"
    CHANGED_MIND = "changed_mind"

    @property
    def is_shipping_refunded(self) -> bool:
        return self is not ReturnReason.CHANGED_MIND


class ReturnForm:
    def __init__(self, reason: str) -> None:
        self.reason = reason

    def is_shipping_refunded(self) -> bool:
        # @sin StringMatchMirrorsEnum
        match self.reason:
            case "damaged" | "wrong_item":
                return True
            case _:
                return False


# @fixed StringMatchMirrorsEnum
class TypedReturnForm:
    def __init__(self, reason: ReturnReason) -> None:
        self.reason = reason

    def is_shipping_refunded(self) -> bool:
        return self.reason.is_shipping_refunded
