# A subscription switched on or off by a conditional expression whose value nothing reads — two
# actions chosen as if they were values. Below it, the FIX: an `if` and an `else`.


class Subscriptions:
    def __init__(self) -> None:
        self.active: set = set()

    def toggle(self, customer: str, on: bool) -> None:
        # @sin ConditionalStatement
        self.active.add(customer) if on else self.active.discard(customer)

    # @fixed ConditionalStatement
    def set_active(self, customer: str, on: bool) -> None:
        if on:
            self.active.add(customer)
        else:
            self.active.discard(customer)
