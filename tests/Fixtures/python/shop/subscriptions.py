# A subscription flipped on or off by a conditional expression whose value nothing reads — two
# actions chosen as if they were values. Below it, the FIX: an `if` and an `else`.


class Subscriptions:
    def __init__(self) -> None:
        self.active: set = set()

    def toggle(self, customer: str) -> None:
        # @sin ConditionalStatement
        self.active.discard(customer) if customer in self.active else self.active.add(customer)

    # @fixed ConditionalStatement
    def flip(self, customer: str) -> None:
        if customer in self.active:
            self.active.discard(customer)
        else:
            self.active.add(customer)
