class BillingSource:
    def settled(self) -> bool:
        return True

    def amount(self) -> int:
        return 1

    def describe(self) -> str:
        return "billing"


# @sin CapabilityProbeHub
class BillingProbe:
    def __init__(self, provider: object) -> None:
        self.provider = provider

    def check(self) -> bool:
        if isinstance(self.provider, BillingSource):
            return self.provider.settled()
        return False

    def rank(self) -> int:
        if isinstance(self.provider, BillingSource):
            return self.provider.amount()
        return 0

    def explain(self) -> str:
        if isinstance(self.provider, BillingSource):
            return self.provider.describe()
        return ""
