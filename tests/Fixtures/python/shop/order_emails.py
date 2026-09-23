# An order confirmation email whose body wraps a computed greeting in fixed lines, kept as a tuple.


class ConfirmationEmail:
    def __init__(self, customer: str, total_cents: int) -> None:
        self.customer = customer
        self.total_cents = total_cents

    def body(self, greeting: str) -> str:
        # @sin AssembledTemplate
        return "\n".join((greeting, "", "Thank you for your order.", f"Total: {self.total_cents / 100:.2f}", "", "The shop"))
