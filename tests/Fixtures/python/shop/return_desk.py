"""Whether a return is still refundable, asked from the desk and from the portal."""

from dataclasses import dataclass


@dataclass(frozen=True)
class Purchase:
    days_since: int
    opened: bool


class RefundPolicy:
    # @sin ComputedBooleanArgument
    def allows(self, expired: bool) -> bool:
        return not expired

    # @fixed ComputedBooleanArgument
    def allows_for(self, purchase: Purchase) -> bool:
        return purchase.days_since <= 30


class Desk:
    def __init__(self, policy: RefundPolicy) -> None:
        self.policy = policy

    def accept(self, purchase: Purchase) -> bool:
        return self.policy.allows(purchase.days_since > 30)


class Portal:
    def __init__(self, policy: RefundPolicy) -> None:
        self.policy = policy

    def offers(self, purchases: list[Purchase]) -> list[Purchase]:
        return [purchase for purchase in purchases if self.policy.allows(expired=purchase.days_since > 30)]

    # @fixed ComputedBooleanArgument
    def offers_honestly(self, purchases: list[Purchase]) -> list[Purchase]:
        return [purchase for purchase in purchases if self.policy.allows_for(purchase)]
