# Whether a payment method shows a card icon, the unlisted methods answered False by the wildcard.
# Below it, the FIX: every member handled, so a new one is a failure, not a quiet False.
from enum import Enum


class PaymentMethod(Enum):
    CARD = 1
    IDEAL = 2
    INVOICE = 3


class UnhandledMethod(Exception):
    @classmethod
    def of(cls, method: PaymentMethod) -> "UnhandledMethod":
        return cls(f"no icon rule for {method.name}")


class CheckoutView:
    def shows_card_icon(self, method: PaymentMethod) -> bool:
        # @sin MatchWildcardReturnsNone
        match method:
            case PaymentMethod.CARD | PaymentMethod.IDEAL:
                return True
            case _:
                return False

    # @fixed MatchWildcardReturnsNone
    def shows_card_icon_for(self, method: PaymentMethod) -> bool:
        match method:
            case PaymentMethod.CARD | PaymentMethod.IDEAL:
                return True
            case PaymentMethod.INVOICE:
                return False
            case _:
                raise UnhandledMethod.of(method)
