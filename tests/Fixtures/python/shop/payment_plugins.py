# A payment plugin that registers itself with the registry it is handed while it is still being
# built. Below it, the FIX: the registry told by whoever decided to install the plugin.


# @sin ConstructorSideEffect
class CardPlugin:
    def __init__(self, registry, fee: float) -> None:
        self.fee = fee
        registry.add("card", self)

    def charge(self, amount: float) -> float:
        return amount * (1 + self.fee)


# @fixed ConstructorSideEffect
class CardPayments:
    def __init__(self, fee: float) -> None:
        self.fee = fee

    def charge(self, amount: float) -> float:
        return amount * (1 + self.fee)


# @fixed ConstructorSideEffect
def install_card_payments(registry, fee: float) -> CardPayments:
    payments = CardPayments(fee)
    registry.add("card", payments)
    return payments
