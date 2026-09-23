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
