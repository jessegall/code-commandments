# Checkout settings whose constant is declared under the attributes it configures. Below it, the FIX:
# the constant first.


class CheckoutSettings:
    currency = "EUR"
    # @sin MemberOutOfOrder
    MAX_LINES = 50

    def allows(self, lines: int) -> bool:
        return lines <= self.MAX_LINES


# @fixed MemberOutOfOrder
class CartSettings:
    MAX_LINES = 50
    currency = "EUR"

    def allows(self, lines: int) -> bool:
        return lines <= self.MAX_LINES


from typing import ClassVar  # noqa: E402


class StoreSettings:
    region: ClassVar[str]


# @fixed MemberOutOfOrder
class DutchStoreSettings(StoreSettings):
    region = "NL"
    GREETING = "Welkom in " + region


class Notice:
    def __init__(self, owner: str, text: str) -> None:
        self.owner, self.text = owner, text


# @fixed MemberOutOfOrder
class ShippingFeature:
    name = "shipping"
    LATE = Notice(name, "a parcel is late")
