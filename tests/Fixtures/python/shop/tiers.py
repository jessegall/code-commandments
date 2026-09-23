# A loyalty rate nested inside another decision — the member test outside, the vip test tucked into
# its branch — so one line holds two choices. Below it, the FIX: guards that leave as soon as they know.


class Loyalty:
    def __init__(self, base: float) -> None:
        self.base = base

    def rate(self, member: bool, vip: bool) -> float:
        # @sin NestedConditional
        return (self.base * 2 if vip else self.base) if member else 0.0

    # @fixed NestedConditional
    def rate_for(self, member: bool, vip: bool) -> float:
        if not member:
            return 0.0
        if vip:
            return self.base * 2
        return self.base
